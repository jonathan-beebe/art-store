<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

use App\Domain\Money\Money;

/**
 * A combination's price: base plus the surcharges its `add_on` option values
 * carry, with a per-variant override for the cells where price is a function
 * of the whole combination (the walnut table's hand-priced dimension matrix)
 * rather than a sum of its parts. When the combination carries at least one
 * `standalone` option value, that option's own price replaces the listing's
 * base price instead of adding to it (`docs/item-configurator.md` §3) — more
 * than one `standalone` selection (multiple standalone axes) sums their
 * prices together as the replacement base.
 */
final readonly class VariantPrice
{
    private function __construct(public Money $amount) {}

    /**
     * @param  list<Money>  $surcharges  `add_on` option values' signed price differences
     * @param  list<Money>  $standalonePrices  `standalone` option values' own absolute prices, empty when the combination selects none
     */
    public static function resolve(Money $basePrice, ?Money $override, array $surcharges, array $standalonePrices = []): self
    {
        if ($override !== null) {
            return new self($override);
        }

        $total = $standalonePrices === [] ? $basePrice : self::sum($standalonePrices);

        foreach ($surcharges as $surcharge) {
            $total = $total->add($surcharge);
        }

        return new self($total);
    }

    /**
     * @param  list<Money>  $amounts
     */
    private static function sum(array $amounts): Money
    {
        $total = Money::zero();

        foreach ($amounts as $amount) {
            $total = $total->add($amount);
        }

        return $total;
    }
}
