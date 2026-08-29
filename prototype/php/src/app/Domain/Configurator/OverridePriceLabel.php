<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

/**
 * Names the first breakdown line for a variant or unit priced by override —
 * a flat dollar amount rather than base-plus-surcharges, so "Base price"
 * would misdescribe what is actually priced. Names the selected combination
 * instead (`"48 in / 30 in"`); an axis-free listing (a serialized unit with
 * no axes, priced on its own) has no combination to name and keeps
 * "Base price".
 */
final class OverridePriceLabel
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * @param  list<string>  $selectedOptionLabels  the buyer's chosen option label per axis, in axis order
     */
    public static function forCombination(array $selectedOptionLabels): string
    {
        return $selectedOptionLabels === [] ? 'Base price' : implode(' / ', $selectedOptionLabels);
    }
}
