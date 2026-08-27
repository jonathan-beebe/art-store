<?php

declare(strict_types=1);

namespace App\Support\Configurator;

use App\Domain\Configurator\VariantPrice;
use App\Domain\Money\Money;
use App\Models\Variant;
use App\Models\VariantOption;
use LogicException;

/**
 * What a combination costs from its choices alone: the listing's base price
 * plus every option value the combination carries a surcharge for, ignoring
 * `price_override_cents` — the "buyers pay … from choices" reading the
 * combinations screen shows, and what its "use $X.XX" link (clearing a set
 * price) switches the combination back to.
 */
final class VariantBuyerPrice
{
    private function __construct() {} // @codeCoverageIgnore

    public static function withoutOverride(Money $basePrice, Variant $variant): Money
    {
        $surcharges = array_values($variant->options->map(
            fn (VariantOption $option): Money => ($option->optionValue ?? throw new LogicException('A variant option always names an option value.'))->surcharge()
        )->all());

        return VariantPrice::resolve($basePrice, null, $surcharges)->amount;
    }
}
