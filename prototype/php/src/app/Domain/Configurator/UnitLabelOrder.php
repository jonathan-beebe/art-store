<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

/**
 * Orders unit labels the way a person reads them — numeric-aware, so "#2"
 * sorts before "#10" — rather than the byte-by-byte string order that puts
 * "#10" before "#2". A thin, tested wrapper over `strnatcmp()` so every
 * caller reaches for the same comparator instead of reimplementing it.
 */
final class UnitLabelOrder
{
    private function __construct() {} // @codeCoverageIgnore

    public static function compare(string $a, string $b): int
    {
        return strnatcmp($a, $b);
    }
}
