<?php

declare(strict_types=1);

namespace App\Configurator;

use App\Domain\Configurator\PricingMode;
use App\Domain\Money\Money;
use App\Models\OptionValue;

/**
 * What a buyer pays choosing one option, independent of whatever else is
 * selected on the listing's other choices: a `standalone` option's own
 * price, or `add_on`'s listing base price plus that option's price
 * difference.
 */
final class OptionBuyerPrice
{
    private function __construct() {} // @codeCoverageIgnore

    public static function forOption(Money $listingPrice, PricingMode $pricingMode, OptionValue $value): Money
    {
        return $pricingMode->isStandalone() ? $value->price() : $listingPrice->add($value->surcharge());
    }
}
