<?php

declare(strict_types=1);

namespace App\Support\Configurator;

use InvalidArgumentException;

/**
 * The seller-facing "% off" field: a plain percent with up to two decimal
 * places, read and written without floats so "12.5" always round-trips to
 * the same `discount_bps` the frozen quantity-break actions store — a
 * seller never learns the word "basis points".
 */
final class QuantityBreakPercent
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * A bare 1–2 digit whole percent, optionally followed by 1–2 decimal
     * places — "0", "100", three-or-more decimals, and a sign all fail to
     * match, which is exactly the range `discount_bps` (1–9999) accepts.
     */
    public static function toBps(string $raw): int
    {
        if (! preg_match('/^(\d{1,2})(?:\.(\d{1,2}))?$/', trim($raw), $matches)) {
            throw new InvalidArgumentException("A discount is a percent between 0.01 and 99.99, like 12.5, got \"{$raw}\".");
        }

        $bps = ((int) $matches[1]) * 100 + (int) str_pad($matches[2] ?? '', 2, '0');

        if ($bps < 1 || $bps > 9999) {
            throw new InvalidArgumentException("A discount is a percent between 0.01 and 99.99, like 12.5, got \"{$raw}\".");
        }

        return $bps;
    }

    public static function isValid(string $raw): bool
    {
        try {
            self::toBps($raw);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * The percent a stored `discount_bps` prefills a tier's field with —
     * whole when it divides evenly, otherwise trimmed to as few decimal
     * places as the value needs.
     */
    public static function format(int $bps): string
    {
        $whole = intdiv($bps, 100);
        $fraction = $bps % 100;

        if ($fraction === 0) {
            return (string) $whole;
        }

        $decimals = rtrim(str_pad((string) $fraction, 2, '0', STR_PAD_LEFT), '0');

        return "{$whole}.{$decimals}";
    }
}
