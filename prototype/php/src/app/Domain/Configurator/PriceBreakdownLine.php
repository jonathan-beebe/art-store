<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

use App\Domain\Money\Money;

/**
 * One line of an itemized price — a label and the cents it adds (or, for a
 * quantity discount, subtracts). The same shape `order_line.price_breakdown_json`
 * will snapshot in FEAT-028.
 */
final readonly class PriceBreakdownLine
{
    private function __construct(public string $label, public Money $amount) {}

    public static function of(string $label, Money $amount): self
    {
        return new self($label, $amount);
    }
}
