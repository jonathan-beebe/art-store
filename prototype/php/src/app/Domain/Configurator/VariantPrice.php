<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

use App\Domain\Money\Money;

/**
 * One pricing mode: base plus the surcharges its option values carry, with a
 * per-variant override for the cells where price is a function of the whole
 * combination (the walnut table's hand-priced dimension matrix) rather than a
 * sum of its parts.
 */
final readonly class VariantPrice
{
    private function __construct(public Money $amount) {}

    /**
     * @param  list<Money>  $surcharges
     */
    public static function resolve(Money $basePrice, ?Money $override, array $surcharges): self
    {
        if ($override !== null) {
            return new self($override);
        }

        $total = $basePrice;

        foreach ($surcharges as $surcharge) {
            $total = $total->add($surcharge);
        }

        return new self($total);
    }
}
