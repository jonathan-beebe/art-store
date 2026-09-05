<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Configurator\ConfiguratorPublishValidation;
use App\Domain\Configurator\PublishIssue;
use App\Domain\Configurator\StandaloneOptionSnapshot;
use App\Domain\Configurator\UnitState;
use App\Domain\Configurator\VariantSnapshot;
use App\Domain\Listings\ListingStatus;
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
use Illuminate\Support\Collection;

/**
 * What blocks each draft in a batch from publishing — the same judgment
 * {@see Listing::publishIssues()} makes for one listing, read here in a
 * fixed number of grouped queries across the whole batch. Every query is
 * an unconditional `whereIn`, so the count holds at any count of drafts
 * and whatever their configurator state.
 *
 * A variant's price is read through {@see Variant::resolvedPrice()} itself,
 * against the batch's own rows wired onto each variant's
 * `options.optionValue.axis` relations.
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
        $optionValuesByAxis = self::optionValuesByAxis(self::axisIds($axesByListing));
        $optionValuesById = self::optionValuesById($optionValuesByAxis);
        self::wireAxisOnto($optionValuesById, self::axisById($axesByListing));

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
            $variants = $variantsByListing[$listing->id] ?? [];

            $issues[$listing->id] = ConfiguratorPublishValidation::check(
                axisIds: array_map(fn (OptionAxis $axis): string => $axis->id, $axes),
                optionCountsPerAxis: array_map(self::optionValuesCount(...), $axes),
                variants: array_map(fn (Variant $variant): VariantSnapshot => self::variantSnapshot(
                    $variant,
                    $listing,
                    $optionsByVariant[$variant->id] ?? [],
                    $optionValuesById,
                    $availableUnitCounts,
                ), $variants),
                modifierCount: $modifierCounts[$listing->id] ?? 0,
                quantityBreakCount: $quantityBreakCounts[$listing->id] ?? 0,
                sectionCount: $sectionCounts[$listing->id] ?? 0,
                requiredAttributePropertyIds: $requiredPropertyIdsByCategory[$listing->category_id] ?? [],
                attributedPropertyIds: $attributedPropertyIdsByListing[$listing->id] ?? [],
                standaloneOptions: self::standaloneOptions($axes, $optionValuesByAxis),
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

        foreach (OptionAxis::query()->whereIn('listing_id', $listingIds)->withCount('optionValues')->get() as $axis) {
            $byListing[$axis->listing_id][] = $axis;
        }

        return $byListing;
    }

    /**
     * @param  array<string, list<OptionAxis>>  $axesByListing
     * @return list<string>
     */
    private static function axisIds(array $axesByListing): array
    {
        $ids = [];

        foreach ($axesByListing as $axes) {
            foreach ($axes as $axis) {
                $ids[] = $axis->id;
            }
        }

        return $ids;
    }

    /**
     * @param  list<string>  $axisIds
     * @return array<string, list<OptionValue>>
     */
    private static function optionValuesByAxis(array $axisIds): array
    {
        $byAxis = [];

        foreach (OptionValue::query()->whereIn('axis_id', $axisIds)->get() as $value) {
            $byAxis[$value->axis_id][] = $value;
        }

        return $byAxis;
    }

    /**
     * @param  array<string, list<OptionValue>>  $optionValuesByAxis
     * @return array<string, OptionValue>
     */
    private static function optionValuesById(array $optionValuesByAxis): array
    {
        $byId = [];

        foreach ($optionValuesByAxis as $values) {
            foreach ($values as $value) {
                $byId[$value->id] = $value;
            }
        }

        return $byId;
    }

    /**
     * @param  array<string, list<OptionAxis>>  $axesByListing
     * @return array<string, OptionAxis>
     */
    private static function axisById(array $axesByListing): array
    {
        $byId = [];

        foreach ($axesByListing as $axes) {
            foreach ($axes as $axis) {
                $byId[$axis->id] = $axis;
            }
        }

        return $byId;
    }

    /**
     * Sets each option value's `axis` relation from the batch's own rows, so
     * {@see Variant::resolvedPrice()} reads a value's pricing mode off an
     * already-loaded relation, once per batch.
     *
     * @param  array<string, OptionValue>  $optionValuesById
     * @param  array<string, OptionAxis>  $axisById
     */
    private static function wireAxisOnto(array $optionValuesById, array $axisById): void
    {
        foreach ($optionValuesById as $value) {
            $value->setRelation('axis', $axisById[$value->axis_id] ?? null);
        }
    }

    private static function optionValuesCount(OptionAxis $axis): int
    {
        $count = $axis->getAttribute('option_values_count');

        return is_numeric($count) ? (int) $count : 0;
    }

    /**
     * @param  list<OptionAxis>  $axes
     * @param  array<string, list<OptionValue>>  $optionValuesByAxis
     * @return list<StandaloneOptionSnapshot>
     */
    private static function standaloneOptions(array $axes, array $optionValuesByAxis): array
    {
        $snapshots = [];

        foreach ($axes as $axis) {
            if (! $axis->pricing_mode->isStandalone()) {
                continue;
            }

            foreach ($optionValuesByAxis[$axis->id] ?? [] as $value) {
                $snapshots[] = new StandaloneOptionSnapshot($value->id, $value->price_cents);
            }
        }

        return $snapshots;
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

    /**
     * Wires this batch's own rows onto the variant's `options` and each
     * option's `optionValue` (already carrying its own wired `axis`) and
     * reads its price through {@see Variant::resolvedPrice()} — the same
     * method a single-listing read calls, against the same rows, at no
     * query of its own.
     *
     * @param  list<VariantOption>  $options  this variant's own option rows
     * @param  array<string, OptionValue>  $optionValuesById
     * @param  array<string, int>  $availableUnitCounts
     */
    private static function variantSnapshot(
        Variant $variant,
        Listing $listing,
        array $options,
        array $optionValuesById,
        array $availableUnitCounts,
    ): VariantSnapshot {
        foreach ($options as $option) {
            $value = $optionValuesById[$option->option_value_id] ?? null;
            $option->setRelation('optionValue', $value);
        }

        $variant->setRelation('options', collect($options));

        return new VariantSnapshot(
            $variant->id,
            $variant->enabled,
            $variant->resolvedPrice($listing->price())->cents,
            $variant->is_serialized,
            $availableUnitCounts[$variant->id] ?? 0,
            array_map(fn (VariantOption $option): string => $option->axis_id, $options),
        );
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

    private static function tally(Unit|Modifier|QuantityBreak|DescriptionSection $row): int
    {
        $value = $row->getAttribute('tally');

        return is_numeric($value) ? (int) $value : 0;
    }
}
