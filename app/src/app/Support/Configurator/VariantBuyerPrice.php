<?php

declare(strict_types=1);

namespace App\Support\Configurator;

use App\Domain\Configurator\PricingMode;
use App\Domain\Configurator\VariantPrice;
use App\Domain\Money\Money;
use App\Models\Variant;
use LogicException;

/**
 * What a combination costs from its choices alone, ignoring
 * `price_override_cents` — the "buyers pay … from choices" reading the
 * combinations screen shows, and what its "use $X.XX" link (clearing a set
 * price) switches the combination back to. The listing's base price plus
 * every `add_on` option value's surcharge, unless the combination carries a
 * `standalone` option — then that option's own price replaces the base
 * entirely (`docs/item-configurator.md` §3). Reads each option's axis off
 * `$variant->options`, so the caller must have eager-loaded
 * `options.optionValue.axis`.
 */
final class VariantBuyerPrice
{
    private function __construct() {} // @codeCoverageIgnore

    public static function withoutOverride(Money $basePrice, Variant $variant): Money
    {
        $standalonePrices = [];
        $addonSurcharges = [];

        foreach ($variant->options as $option) {
            $value = $option->optionValue ?? throw new LogicException('A variant option always names an option value.');
            $axis = $value->axis ?? throw new LogicException('An option value always belongs to an axis.');

            if ($axis->pricing_mode === PricingMode::Standalone) {
                $standalonePrices[] = $value->price();
            } else {
                $addonSurcharges[] = $value->surcharge();
            }
        }

        return VariantPrice::resolve($basePrice, null, $addonSurcharges, $standalonePrices)->amount;
    }
}
