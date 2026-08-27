<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

use App\Domain\Money\Money;

/**
 * Scales one unit's priced lines (base, surcharges, answer add-ons) across
 * the buyer's quantity and appends the tier discount, if one applies, as its
 * own negative line — the assembly behind "base + Σ option surcharges + Σ
 * answer add-ons − quantity discount = total".
 */
final class PriceBreakdownAssembler
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * @param  list<PriceBreakdownLine>  $perUnitLines
     */
    public static function assemble(array $perUnitLines, int $quantity, ?QuantityDiscount $tier): PriceBreakdown
    {
        $scaled = array_map(
            fn (PriceBreakdownLine $line): PriceBreakdownLine => PriceBreakdownLine::of($line->label, $line->amount->multiply($quantity), $line->signed),
            $perUnitLines,
        );

        if ($tier === null) {
            return PriceBreakdown::of($scaled);
        }

        $discount = $tier->discountFor(PriceBreakdown::of($scaled)->total());

        return PriceBreakdown::of([
            ...$scaled,
            PriceBreakdownLine::of("Quantity discount ({$tier->minQty}+)", Money::fromCents(-$discount->cents)),
        ]);
    }
}
