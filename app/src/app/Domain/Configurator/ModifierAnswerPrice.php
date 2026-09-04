<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

use App\Domain\Money\Money;
use InvalidArgumentException;

/**
 * What answering one modifier adds to the price. `Text` and `Measurement`
 * modifiers price the answer itself — `Measurement` on a rate times the
 * buyer's value, rounded to the nearest cent; `Select` prices the chosen
 * option instead, so the modifier's own `add_on_price_cents` never applies to
 * one (`modifier_options.add_on_price_cents` does).
 */
final readonly class ModifierAnswerPrice
{
    private function __construct(public Money $amount) {}

    public static function forText(Money $modifierAddOn): self
    {
        return new self($modifierAddOn);
    }

    public static function forSelect(Money $optionAddOn): self
    {
        return new self($optionAddOn);
    }

    public static function forMeasurement(float $value, ?Money $ratePerUnit): self
    {
        if ($ratePerUnit === null) {
            return new self(Money::zero());
        }

        if ($value < 0) {
            throw new InvalidArgumentException("A measurement answer must not be negative, got {$value}.");
        }

        return new self(Money::fromCents((int) round($value * $ratePerUnit->cents)));
    }
}
