<?php

declare(strict_types=1);

namespace App\Configurator;

use App\Domain\Configurator\QuantityDiscount;
use App\Domain\Money\Money;

/**
 * The "≈ $X.XX per item" chip a quantity-discount tier shows next to its
 * own row — the base price with that one tier's discount applied, so a
 * seller reads the effect of a percent without computing it by hand.
 */
final class QuantityBreakUnitPrice
{
    private function __construct() {} // @codeCoverageIgnore

    public static function resolve(Money $basePrice, QuantityDiscount $tier): Money
    {
        return $basePrice->subtract($tier->discountFor($basePrice));
    }
}
