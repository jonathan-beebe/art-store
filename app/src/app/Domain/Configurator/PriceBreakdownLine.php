<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

use App\Domain\Money\Money;

/**
 * One line of an itemized price — a label and the cents it adds (or, for a
 * quantity discount, subtracts). The same shape `order_line.price_breakdown_json`
 * will snapshot in FEAT-028.
 *
 * `$signed` tells the price panel whether a positive amount reads as a delta
 * on top of something else ("+$32.00" — an add-on surcharge, an answer's
 * add-on price, the quantity discount) or as a price in its own right
 * ("$18.00" — the listing's base price, or a `standalone` option's own
 * price), which never takes a leading "+" regardless of its position in the
 * list.
 */
final readonly class PriceBreakdownLine
{
    private function __construct(public string $label, public Money $amount, public bool $signed) {}

    public static function of(string $label, Money $amount, bool $signed = true): self
    {
        return new self($label, $amount, $signed);
    }
}
