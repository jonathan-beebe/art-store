<?php

declare(strict_types=1);

namespace App\Support\Configurator;

use App\Domain\Money\Money;

/**
 * What a buyer pays choosing one option on its own: the listing's base price
 * plus that option's price difference, independent of whatever else is
 * selected on the listing's other choices.
 */
final class OptionBuyerPrice
{
    private function __construct() {} // @codeCoverageIgnore

    public static function forOption(Money $listingPrice, Money $priceDifference): Money
    {
        return $listingPrice->add($priceDifference);
    }
}
