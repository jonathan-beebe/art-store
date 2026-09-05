<?php

declare(strict_types=1);

namespace App\Configurator;

use App\Domain\Configurator\ModifierAnswerPrice;
use App\Domain\Configurator\ModifierKind;
use App\Domain\Configurator\OverridePriceLabel;
use App\Domain\Configurator\PriceBreakdown;
use App\Domain\Configurator\PriceBreakdownAssembler;
use App\Domain\Configurator\PriceBreakdownLine;
use App\Domain\Configurator\PricingMode;
use App\Domain\Configurator\QuantityDiscount;
use App\Domain\Money\Money;
use App\Models\Listing;
use App\Models\Modifier;
use App\Models\ModifierOption;
use App\Models\OptionAxis;
use App\Models\OptionValue;
use App\Models\QuantityBreak;
use App\Models\Unit;
use App\Models\Variant;
use Illuminate\Support\Collection;
use LogicException;

/**
 * The itemized price for one configuration, re-resolved live against the
 * listing's current rows every time — a cart line's price is never stored,
 * so this is what both the configurator page and the cart page call.
 */
final class ConfigurationPricer
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * @param  list<OptionValue>  $selectedOptionValues  the buyer's chosen value per axis, empty for an axis-free listing
     * @param  array<string, string>  $rawAnswers  modifier id => raw answer value
     */
    public static function price(
        Listing $listing,
        array $selectedOptionValues,
        ?Variant $variant,
        ?Unit $unit,
        array $rawAnswers,
        int $quantity,
    ): PriceBreakdown {
        $listing->loadMissing(['modifiers.options', 'quantityBreaks', 'optionAxes']);

        $variantOverride = $variant?->price_override_cents === null ? null : Money::fromCents($variant->price_override_cents);
        $unitOverride = $unit?->price_override_cents === null ? null : Money::fromCents($unit->price_override_cents);
        $effectiveOverride = $unitOverride ?? $variantOverride;

        $perUnitLines = $effectiveOverride !== null
            ? [PriceBreakdownLine::of(self::combinationLabel($selectedOptionValues), $effectiveOverride, signed: false)]
            : self::baseAndSurchargeLines($listing, $selectedOptionValues);

        $selectedIds = array_map(fn (OptionValue $value): string => $value->id, $selectedOptionValues);

        foreach ($listing->modifiers as $modifier) {
            if (! $modifier->appliesTo($selectedIds)) {
                continue;
            }

            $raw = $rawAnswers[$modifier->id] ?? null;

            if ($raw === null || $raw === '') {
                continue;
            }

            $amount = self::priceAnswer($modifier, $raw);

            if (! $amount->isZero()) {
                $perUnitLines[] = PriceBreakdownLine::of(self::answerLabel($modifier, $raw), $amount);
            }
        }

        $tier = QuantityDiscount::bestFor(
            array_values($listing->quantityBreaks->map(fn (QuantityBreak $break): QuantityDiscount => $break->toDomain())->all()),
            $quantity,
        );

        return PriceBreakdownAssembler::assemble($perUnitLines, $quantity, $tier);
    }

    /**
     * @param  list<OptionValue>  $selectedOptionValues
     */
    private static function combinationLabel(array $selectedOptionValues): string
    {
        return OverridePriceLabel::forCombination(array_map(fn (OptionValue $value): string => $value->label, $selectedOptionValues));
    }

    /**
     * A listing with no `standalone` axis keeps today's shape exactly: one
     * "Base price" line, plus one line per option value that actually
     * surcharges. A listing with at least one `standalone` axis drops "Base
     * price" — there is no single base to name — and instead itemizes every
     * selected option, standalone ones at their own absolute price,
     * `add_on` ones at their signed surcharge, unconditionally: with the
     * base line gone, a zero-cost `add_on` selection ("Frame: Unframed —
     * +$0.00") is the only place that choice still shows on the panel.
     *
     * @param  list<OptionValue>  $selectedOptionValues
     * @return list<PriceBreakdownLine>
     */
    private static function baseAndSurchargeLines(Listing $listing, array $selectedOptionValues): array
    {
        $axisById = $listing->optionAxes->keyBy('id');
        $hasStandaloneAxis = $axisById->contains(fn (OptionAxis $axis): bool => $axis->pricing_mode === PricingMode::Standalone);

        return $hasStandaloneAxis
            ? self::itemizedLines($axisById, $selectedOptionValues)
            : self::baseWithSurchargeLines($listing, $selectedOptionValues);
    }

    /**
     * @param  list<OptionValue>  $selectedOptionValues
     * @return list<PriceBreakdownLine>
     */
    private static function baseWithSurchargeLines(Listing $listing, array $selectedOptionValues): array
    {
        $lines = [PriceBreakdownLine::of('Base price', $listing->price(), signed: false)];

        foreach ($selectedOptionValues as $value) {
            if ($value->surcharge_cents !== 0) {
                $lines[] = PriceBreakdownLine::of($value->label, $value->surcharge());
            }
        }

        return $lines;
    }

    /**
     * @param  Collection<string, OptionAxis>  $axisById
     * @param  list<OptionValue>  $selectedOptionValues
     * @return list<PriceBreakdownLine>
     */
    private static function itemizedLines(Collection $axisById, array $selectedOptionValues): array
    {
        $lines = [];

        foreach ($selectedOptionValues as $value) {
            $axis = self::axisFor($axisById, $value);
            $isStandalone = $axis->pricing_mode === PricingMode::Standalone;
            $amount = $isStandalone ? $value->price() : $value->surcharge();
            $lines[] = PriceBreakdownLine::of("{$axis->name}: {$value->label}", $amount, signed: ! $isStandalone);
        }

        return $lines;
    }

    /**
     * @param  Collection<string, OptionAxis>  $axisById
     */
    private static function axisFor(Collection $axisById, OptionValue $value): OptionAxis
    {
        $axis = $axisById->get($value->axis_id);

        return $axis instanceof OptionAxis ? $axis : throw new LogicException('An option value always belongs to one of the listing’s axes.');
    }

    private static function priceAnswer(Modifier $modifier, string $raw): Money
    {
        return match ($modifier->kind) {
            ModifierKind::Text => ModifierAnswerPrice::forText(Money::fromCents($modifier->add_on_price_cents))->amount,
            ModifierKind::Select => self::priceSelectAnswer($modifier, $raw),
            ModifierKind::Measurement => ModifierAnswerPrice::forMeasurement(
                is_numeric($raw) ? (float) $raw : 0.0,
                $modifier->rate_cents_per_unit === null ? null : Money::fromCents($modifier->rate_cents_per_unit),
            )->amount,
        };
    }

    private static function priceSelectAnswer(Modifier $modifier, string $optionId): Money
    {
        $option = $modifier->options->firstWhere('id', $optionId);

        return $option instanceof ModifierOption ? ModifierAnswerPrice::forSelect($option->addOn())->amount : Money::zero();
    }

    private static function answerLabel(Modifier $modifier, string $raw): string
    {
        if ($modifier->kind === ModifierKind::Select) {
            $option = $modifier->options->firstWhere('id', $raw);

            return $option instanceof ModifierOption ? "{$modifier->prompt}: {$option->label}" : $modifier->prompt;
        }

        return $modifier->prompt;
    }
}
