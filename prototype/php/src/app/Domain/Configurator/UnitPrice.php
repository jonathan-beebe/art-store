<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

use App\Domain\Money\Money;

/**
 * The price precedence a serialized variant adds one more level to: a unit's
 * own override beats the variant's, which beats base plus surcharges — "per-
 * unit price override respected" from the customer flow.
 */
final class UnitPrice
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * @param  list<Money>  $surcharges
     * @param  list<Money>  $standalonePrices
     */
    public static function resolve(?Money $unitOverride, ?Money $variantOverride, Money $basePrice, array $surcharges, array $standalonePrices = []): Money
    {
        if ($unitOverride !== null) {
            return $unitOverride;
        }

        return VariantPrice::resolve($basePrice, $variantOverride, $surcharges, $standalonePrices)->amount;
    }
}
