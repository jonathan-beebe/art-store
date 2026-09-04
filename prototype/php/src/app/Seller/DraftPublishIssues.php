<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Configurator\ConfiguratorPublishValidation;
use App\Domain\Configurator\PricingMode;
use App\Domain\Configurator\PublishIssue;
use App\Domain\Configurator\StandaloneOptionSnapshot;
use App\Domain\Configurator\UnitState;
use App\Domain\Configurator\VariantPrice;
use App\Domain\Configurator\VariantSnapshot;
use App\Domain\Listings\ListingStatus;
use App\Domain\Money\Money;
use App\Models\CategoryProperty;
use App\Models\DescriptionSection;
use App\Models\Listing;
use App\Models\ListingAttribute;
use App\Models\Modifier;
use App\Models\OptionAxis;
use App\Models\OptionValue;
use App\Models\QuantityBreak;
use App\Models\Unit;
use App\Models\Variant;
use App\Models\VariantOption;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use LogicException;

/**
 * What blocks each draft in a batch from publishing — the same judgment
 * {@see Listing::publishIssues()} makes for one listing, read here in a
 * fixed number of grouped queries across the whole batch instead of one
 * listing's worth of queries apiece.
 */
final readonly class DraftPublishIssues
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * @param  Collection<int, Listing>  $listings  any status; only drafts are judged
     * @return array<string, list<PublishIssue>> keyed by listing id, draft listings only
     */
    public static function forListings(Collection $listings): array
    {
        $drafts = $listings->filter(fn (Listing $listing): bool => $listing->status === ListingStatus::Draft)->values();

        if ($drafts->isEmpty()) {
            return [];
        }

        /** @var list<string> $listingIds */
        $listingIds = array_values($drafts->pluck('id')->all());

        $axesByListing = self::axesByListing($listingIds);
        $variantsByListing = self::variantsByListing($listingIds);
        $optionsByVariant = self::optionsByVariant(self::variantIds($variantsByListing));
        $availableUnitCounts = self::availableUnitCounts(self::variantIds($variantsByListing));
        $requiredPropertyIdsByCategory = self::requiredPropertyIdsByCategory($drafts);
        $attributedPropertyIdsByListing = self::attributedPropertyIdsByListing($listingIds);
        $modifierCounts = self::modifierCounts($listingIds);
        $quantityBreakCounts = self::quantityBreakCounts($listingIds);
        $sectionCounts = self::sectionCounts($listingIds);

        $issues = [];

        /** @var Listing $listing */
        foreach ($drafts as $listing) {
            $axes = $axesByListing[$listing->id] ?? [];
            $optionValuesById = self::optionValuesById($axes);
            $pricingModeByAxisId = self::pricingModeByAxisId($axes);
            $variants = $variantsByListing[$listing->id] ?? [];

            $issues[$listing->id] = ConfiguratorPublishValidation::check(
                axisIds: array_map(fn (OptionAxis $axis): string => $axis->id, $axes),
                optionCountsPerAxis: array_map(self::optionValuesCount(...), $axes),
                variants: array_map(fn (Variant $variant): VariantSnapshot => self::variantSnapshot(
                    $variant,
                    $listing->price(),
                    $optionsByVariant[$variant->id] ?? [],
                    $pricingModeByAxisId,
                    $optionValuesById,
                    $availableUnitCounts,
                ), $variants),
                modifierCount: $modifierCounts[$listing->id] ?? 0,
                quantityBreakCount: $quantityBreakCounts[$listing->id] ?? 0,
                sectionCount: $sectionCounts[$listing->id] ?? 0,
                requiredAttributePropertyIds: $requiredPropertyIdsByCategory[$listing->category_id] ?? [],
                attributedPropertyIds: $attributedPropertyIdsByListing[$listing->id] ?? [],
                standaloneOptions: self::standaloneOptions($axes),
            );
        }

        return $issues;
    }

    /**
     * @param  list<string>  $listingIds
     * @return array<string, list<OptionAxis>>
     */
    private static function axesByListing(array $listingIds): array
    {
        $byListing = [];

        foreach (OptionAxis::query()->whereIn('listing_id', $listingIds)->withCount('optionValues')->with('optionValues')->get() as $axis) {
            $byListing[$axis->listing_id][] = $axis;
        }

        return $byListing;
    }

    /**
     * @param  list<string>  $listingIds
     * @return array<string, list<Variant>>
     */
    private static function variantsByListing(array $listingIds): array
    {
        $byListing = [];

        foreach (Variant::query()->whereIn('listing_id', $listingIds)->get() as $variant) {
            $byListing[$variant->listing_id][] = $variant;
        }

        return $byListing;
    }

    /**
     * @param  array<string, list<Variant>>  $variantsByListing
     * @return list<string>
     */
    private static function variantIds(array $variantsByListing): array
    {
        $ids = [];

        foreach ($variantsByListing as $variants) {
            foreach ($variants as $variant) {
                $ids[] = $variant->id;
            }
        }

        return $ids;
    }

    /**
     * @param  list<string>  $variantIds
     * @return array<string, list<VariantOption>>
     */
    private static function optionsByVariant(array $variantIds): array
    {
        $byVariant = [];

        foreach (VariantOption::query()->whereIn('variant_id', $variantIds)->get() as $option) {
            $byVariant[$option->variant_id][] = $option;
        }

        return $byVariant;
    }

    private static function optionValuesCount(OptionAxis $axis): int
    {
        return self::intAttribute($axis, 'option_values_count');
    }

    /**
     * A grouped read's count column, off a row whose model carries no
     * `tally` property of its own.
     */
    private static function tally(Model $row): int
    {
        return self::intAttribute($row, 'tally');
    }

    private static function intAttribute(Model $model, string $key): int
    {
        $value = $model->getAttribute($key);

        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * @param  list<OptionAxis>  $axes
     * @return array<string, OptionValue>
     */
    private static function optionValuesById(array $axes): array
    {
        $byId = [];

        foreach ($axes as $axis) {
            foreach ($axis->optionValues as $value) {
                $byId[$value->id] = $value;
            }
        }

        return $byId;
    }

    /**
     * @param  list<OptionAxis>  $axes
     * @return array<string, PricingMode>
     */
    private static function pricingModeByAxisId(array $axes): array
    {
        $byId = [];

        foreach ($axes as $axis) {
            $byId[$axis->id] = $axis->pricing_mode;
        }

        return $byId;
    }

    /**
     * @param  list<OptionAxis>  $axes
     * @return list<StandaloneOptionSnapshot>
     */
    private static function standaloneOptions(array $axes): array
    {
        $snapshots = [];

        foreach ($axes as $axis) {
            if ($axis->pricing_mode !== PricingMode::Standalone) {
                continue;
            }

            foreach ($axis->optionValues as $value) {
                $snapshots[] = new StandaloneOptionSnapshot($value->id, $value->price_cents);
            }
        }

        return $snapshots;
    }

    /**
     * @param  list<VariantOption>  $options  this variant's own option rows
     * @param  array<string, PricingMode>  $pricingModeByAxisId
     * @param  array<string, OptionValue>  $optionValuesById
     * @param  array<string, int>  $availableUnitCounts
     */
    private static function variantSnapshot(
        Variant $variant,
        Money $basePrice,
        array $options,
        array $pricingModeByAxisId,
        array $optionValuesById,
        array $availableUnitCounts,
    ): VariantSnapshot {
        return new VariantSnapshot(
            $variant->id,
            $variant->enabled,
            self::resolvedPriceCents($variant, $basePrice, $options, $pricingModeByAxisId, $optionValuesById),
            $variant->is_serialized,
            $availableUnitCounts[$variant->id] ?? 0,
            array_map(fn (VariantOption $option): string => $option->axis_id, $options),
        );
    }

    /**
     * @param  list<VariantOption>  $options
     * @param  array<string, PricingMode>  $pricingModeByAxisId
     * @param  array<string, OptionValue>  $optionValuesById
     */
    private static function resolvedPriceCents(
        Variant $variant,
        Money $basePrice,
        array $options,
        array $pricingModeByAxisId,
        array $optionValuesById,
    ): int {
        $standalonePrices = [];
        $addonSurcharges = [];

        foreach ($options as $option) {
            $value = $optionValuesById[$option->option_value_id] ?? throw new LogicException('A variant option always names an option value.');
            $mode = $pricingModeByAxisId[$option->axis_id] ?? throw new LogicException('An option value always belongs to an axis.');

            if ($mode === PricingMode::Standalone) {
                $standalonePrices[] = $value->price();
            } else {
                $addonSurcharges[] = $value->surcharge();
            }
        }

        return VariantPrice::resolve(
            $basePrice,
            $variant->price_override_cents === null ? null : Money::fromCents($variant->price_override_cents),
            $addonSurcharges,
            $standalonePrices,
        )->amount->cents;
    }

    /**
     * @param  list<string>  $variantIds
     * @return array<string, int>
     */
    private static function availableUnitCounts(array $variantIds): array
    {
        $counts = [];

        foreach (Unit::query()->whereIn('variant_id', $variantIds)->where('state', UnitState::Available)
            ->select('variant_id')->selectRaw('count(*) as tally')->groupBy('variant_id')->get() as $row) {
            $counts[$row->variant_id] = self::tally($row);
        }

        return $counts;
    }

    /**
     * @param  Collection<int, Listing>  $drafts
     * @return array<string, list<string>> category id => required attribute property ids
     */
    private static function requiredPropertyIdsByCategory(Collection $drafts): array
    {
        $categoryIds = array_values($drafts->pluck('category_id')->filter()->unique()->all());
        $byCategory = [];

        foreach (CategoryProperty::query()->whereIn('category_id', $categoryIds)
            ->where('usable_as_attribute', true)
            ->where('required', true)
            ->get() as $property) {
            $byCategory[$property->category_id][] = $property->property_id;
        }

        return $byCategory;
    }

    /**
     * @param  list<string>  $listingIds
     * @return array<string, list<string>>
     */
    private static function attributedPropertyIdsByListing(array $listingIds): array
    {
        $byListing = [];

        foreach (ListingAttribute::query()->whereIn('listing_id', $listingIds)->get() as $attribute) {
            $byListing[$attribute->listing_id][$attribute->property_id] = true;
        }

        return array_map(fn (array $propertyIds): array => array_keys($propertyIds), $byListing);
    }

    /**
     * @param  list<string>  $listingIds
     * @return array<string, int>
     */
    private static function modifierCounts(array $listingIds): array
    {
        $counts = [];

        foreach (Modifier::query()->whereIn('listing_id', $listingIds)->select('listing_id')->selectRaw('count(*) as tally')->groupBy('listing_id')->get() as $row) {
            $counts[$row->listing_id] = self::tally($row);
        }

        return $counts;
    }

    /**
     * @param  list<string>  $listingIds
     * @return array<string, int>
     */
    private static function quantityBreakCounts(array $listingIds): array
    {
        $counts = [];

        foreach (QuantityBreak::query()->whereIn('listing_id', $listingIds)->select('listing_id')->selectRaw('count(*) as tally')->groupBy('listing_id')->get() as $row) {
            $counts[$row->listing_id] = self::tally($row);
        }

        return $counts;
    }

    /**
     * @param  list<string>  $listingIds
     * @return array<string, int>
     */
    private static function sectionCounts(array $listingIds): array
    {
        $counts = [];

        foreach (DescriptionSection::query()->whereIn('listing_id', $listingIds)->select('listing_id')->selectRaw('count(*) as tally')->groupBy('listing_id')->get() as $row) {
            $counts[$row->listing_id] = self::tally($row);
        }

        return $counts;
    }
}
