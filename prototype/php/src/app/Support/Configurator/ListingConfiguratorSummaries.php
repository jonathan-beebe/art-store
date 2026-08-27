<?php

declare(strict_types=1);

namespace App\Support\Configurator;

use App\Domain\Configurator\ModifierKind;
use App\Domain\Configurator\PricingMode;
use App\Domain\Configurator\UnitState;
use App\Domain\Money\Money;
use App\Models\DescriptionSection;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\Modifier;
use App\Models\ModifierOption;
use App\Models\ModifierScope;
use App\Models\OptionValue;
use App\Models\QuantityBreak;
use App\Models\Unit;
use App\Models\Variant;
use Illuminate\Support\Collection;
use LogicException;

/**
 * The seller edit hub renders each configurator area as either a one-line
 * invitation (nothing set up yet) or a summary card (something is). This
 * builds the summary content for the areas that have it, reading straight off
 * a listing's rows; a `null` return is the signal the view reads as "show the
 * invitation instead".
 */
final class ListingConfiguratorSummaries
{
    /**
     * A variant at or below this available quantity reads as low on stock on
     * the choices summary card.
     */
    private const int LOW_STOCK_MAX_QUANTITY = 3;

    /**
     * The most option labels a choice's summary line names before the rest
     * collapse into an "N more" count.
     */
    private const int MAX_DISPLAYED_OPTIONS = 3;

    private function __construct() {} // @codeCoverageIgnore

    /**
     * @return null|array{axes: list<array{name: string, pricingMode: PricingMode, displayedLabels: list<string>, priceDeltas: list<string>, moreCount: int}>, offeredCount: int, totalCombinations: int, lowStockCount: int, combinationsUrl: string}
     */
    public static function choices(Listing $listing): ?array
    {
        $axes = $listing->optionAxes()->with('optionValues')->orderBy('position')->get();

        if ($axes->isEmpty()) {
            return null;
        }

        $totalCombinations = 1;
        $axisLines = [];

        foreach ($axes as $axis) {
            $values = $axis->optionValues->sortBy('position')->values();
            $totalCombinations *= $values->count();

            $displayed = $values->take(self::MAX_DISPLAYED_OPTIONS);
            $isStandalone = $axis->pricing_mode === PricingMode::Standalone;

            $axisLines[] = [
                'name' => $axis->name,
                'pricingMode' => $axis->pricing_mode,
                'displayedLabels' => array_values($displayed->map(fn (OptionValue $value): string => $value->label)->all()),
                // A `standalone` option's price is a price in its own right,
                // not a delta on anything — every displayed option names one,
                // unfiltered and unsigned ("$18.00"); an `add_on` option's
                // price difference stays filtered to the ones that actually
                // move the price, signed as before ("+$6.00").
                'priceDeltas' => $isStandalone
                    ? array_values($displayed->map(fn (OptionValue $value): string => Money::fromCents($value->price_cents ?? 0)->format())->all())
                    : array_values($displayed
                        ->filter(fn (OptionValue $value): bool => $value->surcharge_cents !== 0)
                        ->map(fn (OptionValue $value): string => self::signedAmount($value->surcharge_cents))
                        ->all()),
                'moreCount' => max(0, $values->count() - self::MAX_DISPLAYED_OPTIONS),
            ];
        }

        $variants = $listing->variants()->get();

        return [
            'axes' => $axisLines,
            'offeredCount' => $variants->where('enabled', true)->count(),
            'totalCombinations' => $totalCombinations,
            'lowStockCount' => $variants->filter(fn (Variant $variant): bool => $variant->enabled
                && ! $variant->is_serialized
                && $variant->quantity !== null
                && $variant->quantity <= self::LOW_STOCK_MAX_QUANTITY)->count(),
            'combinationsUrl' => route('seller.listings.variants.index', $listing),
        ];
    }

    /**
     * @return null|list<array{prompt: string, priceLabel: ?string, required: bool, scopeNote: ?string}>
     */
    public static function questions(Listing $listing): ?array
    {
        $modifiers = $listing->modifiers()->with(['options', 'scopes.optionValue.axis'])->orderBy('position')->get();

        if ($modifiers->isEmpty()) {
            return null;
        }

        return array_values($modifiers->map(fn (Modifier $modifier): array => [
            'prompt' => $modifier->prompt,
            'priceLabel' => self::priceLabel($modifier),
            'required' => $modifier->required,
            'scopeNote' => self::scopeNote($modifier->scopes),
        ])->all());
    }

    public static function discountsLine(Listing $listing): ?string
    {
        $breaks = $listing->quantityBreaks()->orderBy('min_qty')->get();

        if ($breaks->isEmpty()) {
            return null;
        }

        return implode(' · ', array_values($breaks->map(
            fn (QuantityBreak $break): string => "{$break->min_qty} or more — ".self::percentLabel($break->discount_bps).' off each'
        )->all()));
    }

    public static function sectionsLine(Listing $listing): ?string
    {
        $sections = $listing->descriptionSections()->orderBy('position')->get();

        if ($sections->isEmpty()) {
            return null;
        }

        return implode(' · ', array_values($sections->map(fn (DescriptionSection $section): string => (string) $section->title)->all()));
    }

    /**
     * The hub's Images row: cover-first thumbnail urls and how many the
     * listing carries in total — every listing has at least a placeholder
     * cover, so unlike the other summaries here this never reads as "not set
     * up yet".
     *
     * @return array{urls: list<string>, count: int}
     */
    public static function images(Listing $listing): array
    {
        $images = $listing->images()->orderBy('position')->get();

        return [
            'urls' => array_values($images->map(fn (ListingImage $image): string => $image->url())->all()),
            'count' => $images->count(),
        ];
    }

    /**
     * @return null|array{total: int, available: int, sold: int, url: string}
     */
    public static function pieces(Listing $listing): ?array
    {
        $serializedVariantIds = $listing->variants()->where('is_serialized', true)->pluck('id');

        if ($serializedVariantIds->isEmpty()) {
            return null;
        }

        $units = Unit::query()->whereIn('variant_id', $serializedVariantIds)->get();

        return [
            'total' => $units->count(),
            'available' => $units->where('state', UnitState::Available)->count(),
            'sold' => $units->where('state', UnitState::Sold)->count(),
            'url' => route('seller.listings.variants.index', $listing),
        ];
    }

    private static function priceLabel(Modifier $modifier): ?string
    {
        return match ($modifier->kind) {
            ModifierKind::Text => $modifier->add_on_price_cents > 0 ? self::signedAmount($modifier->add_on_price_cents) : null,
            ModifierKind::Measurement => $modifier->rate_cents_per_unit !== null && $modifier->rate_cents_per_unit > 0 ? self::signedAmount($modifier->rate_cents_per_unit) : null,
            ModifierKind::Select => self::selectPriceLabel($modifier->options),
        };
    }

    /**
     * @param  Collection<int, ModifierOption>  $options
     */
    private static function selectPriceLabel(Collection $options): ?string
    {
        $priced = $options->first(fn (ModifierOption $option): bool => $option->add_on_price_cents > 0);

        return $priced instanceof ModifierOption ? self::signedAmount($priced->add_on_price_cents) : null;
    }

    /**
     * @param  Collection<int, ModifierScope>  $scopes
     */
    private static function scopeNote(Collection $scopes): ?string
    {
        if ($scopes->isEmpty()) {
            return null;
        }

        $labelsByAxis = [];

        foreach ($scopes as $scope) {
            $value = $scope->optionValue ?? throw new LogicException('A modifier scope always names an option value.');
            $axis = $value->axis ?? throw new LogicException('An option value always belongs to an axis.');

            $labelsByAxis[$axis->name][] = $value->label;
        }

        $clauses = array_map(
            fn (string $axisName, array $labels): string => "{$axisName} is ".implode(' or ', $labels),
            array_keys($labelsByAxis),
            array_values($labelsByAxis),
        );

        return 'only asked when '.implode(' and ', $clauses);
    }

    private static function signedAmount(int $cents): string
    {
        return ($cents > 0 ? '+' : '').Money::fromCents($cents)->format();
    }

    private static function percentLabel(int $basisPoints): string
    {
        return rtrim(rtrim(number_format($basisPoints / 100, 2), '0'), '.').'%';
    }
}
