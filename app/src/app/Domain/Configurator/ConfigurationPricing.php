<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

/**
 * The itemized price for one configuration (`docs/item-configurator.md`
 * §3), computed from plain values. A cart line's price is never stored, so
 * the configurator page and the cart line both call this against the
 * listing's current rows, folded into a {@see PricingConfiguration} by the
 * model that holds them.
 */
final class ConfigurationPricing
{
    private function __construct() {} // @codeCoverageIgnore

    public static function price(PricingConfiguration $configuration, int $quantity): PriceBreakdown
    {
        $perUnitLines = $configuration->override !== null
            ? [PriceBreakdownLine::of(self::combinationLabel($configuration->selected), $configuration->override, signed: false)]
            : self::baseAndSurchargeLines($configuration);

        $selectedIds = array_map(fn (PricedOption $option): string => $option->id, $configuration->selected);

        foreach ($configuration->modifiers as $modifier) {
            if (! $modifier->appliesTo($selectedIds)) {
                continue;
            }

            $raw = $configuration->answers[$modifier->id] ?? null;

            if ($raw === null || $raw === '') {
                continue;
            }

            $amount = $modifier->priceAnswer($raw);

            if (! $amount->isZero()) {
                $perUnitLines[] = PriceBreakdownLine::of($modifier->answerLabel($raw), $amount);
            }
        }

        $tier = QuantityDiscount::bestFor($configuration->tiers, $quantity);

        return PriceBreakdownAssembler::assemble($perUnitLines, $quantity, $tier);
    }

    /**
     * @param  list<PricedOption>  $selected
     */
    private static function combinationLabel(array $selected): string
    {
        return OverridePriceLabel::forCombination(array_map(fn (PricedOption $option): string => $option->label, $selected));
    }

    /**
     * A listing with no `standalone` axis keeps one "Base price" line, plus
     * one line per option value that surcharges. A listing with at least one
     * `standalone` axis has no single base to name, so it itemizes every
     * selected option: standalone ones at their own absolute price, `add_on`
     * ones at their signed surcharge, a zero surcharge included, because
     * with the base line gone that line is the one place the choice shows.
     *
     * @return list<PriceBreakdownLine>
     */
    private static function baseAndSurchargeLines(PricingConfiguration $configuration): array
    {
        if ($configuration->hasStandaloneAxis) {
            return array_map(
                fn (PricedOption $option): PriceBreakdownLine => PriceBreakdownLine::of(
                    "{$option->axisName}: {$option->label}",
                    $option->amount(),
                    signed: ! $option->standalone,
                ),
                $configuration->selected,
            );
        }

        $lines = [PriceBreakdownLine::of('Base price', $configuration->basePrice, signed: false)];

        foreach ($configuration->selected as $option) {
            if (! $option->surcharge->isZero()) {
                $lines[] = PriceBreakdownLine::of($option->label, $option->surcharge);
            }
        }

        return $lines;
    }
}
