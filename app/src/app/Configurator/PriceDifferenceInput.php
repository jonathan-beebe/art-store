<?php

declare(strict_types=1);

namespace App\Configurator;

use App\Domain\Money\Money;
use InvalidArgumentException;

/**
 * The seller-facing "Price difference" field: a signed dollar amount that
 * reads as an em dash when there is none, and writes back as "+$6.00" or
 * "-$3.50" so the value this class formats always parses back to the same
 * cents.
 */
final class PriceDifferenceInput
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * Blank and an em dash both mean "no difference". A leading "$" or "+"
     * is stripped before the rest is read as a dollar amount.
     */
    public static function parseCents(?string $raw): int
    {
        $trimmed = trim((string) $raw);

        if ($trimmed === '' || $trimmed === '—') {
            return 0;
        }

        return Money::fromDollars(str_replace(['$', '+'], '', $trimmed))->cents;
    }

    public static function isValid(?string $raw): bool
    {
        try {
            self::parseCents($raw);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    public static function format(int $cents): string
    {
        if ($cents === 0) {
            return '—';
        }

        $money = Money::fromCents($cents);

        return $cents > 0 ? '+'.$money->format() : $money->format();
    }
}
