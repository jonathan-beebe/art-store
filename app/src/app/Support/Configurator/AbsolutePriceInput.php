<?php

declare(strict_types=1);

namespace App\Support\Configurator;

use App\Domain\Money\Money;
use InvalidArgumentException;

/**
 * The seller-facing "Price" field on a `standalone` axis's option — a plain,
 * non-negative dollar amount ("18.00", "$18.00"), unlike {@see PriceDifferenceInput}'s
 * signed "price difference" field on an `add_on` axis's option.
 */
final class AbsolutePriceInput
{
    private function __construct() {} // @codeCoverageIgnore

    public static function parseCents(string $raw): int
    {
        $trimmed = str_replace('$', '', trim($raw));
        $money = Money::fromDollars($trimmed);

        if ($money->cents < 0) {
            throw new InvalidArgumentException("A price must not be negative, got \"{$raw}\".");
        }

        return $money->cents;
    }

    public static function isValid(?string $raw): bool
    {
        if ($raw === null || trim($raw) === '') {
            return false;
        }

        try {
            self::parseCents($raw);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }
}
