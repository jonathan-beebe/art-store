<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

use App\Domain\Money\Money;

/**
 * One choice on a `select` modifier: its id (the raw answer a form posts),
 * the label a breakdown line names, and the add-on it charges.
 */
final readonly class PricedModifierOption
{
    private function __construct(public string $id, public string $label, public Money $addOn) {}

    public static function of(string $id, string $label, Money $addOn): self
    {
        return new self($id, $label, $addOn);
    }
}
