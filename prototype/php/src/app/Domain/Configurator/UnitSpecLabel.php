<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

/**
 * Humanizes one `specs_json` entry for display — `height_mm: 205` becomes
 * "Height: 205 mm". A trailing segment that names a known unit is split off
 * and shown after the value; everything else falls back to a title-cased key
 * with no unit, so an unanticipated spec still reads as English rather than
 * a raw column name.
 */
final class UnitSpecLabel
{
    private const array KNOWN_UNITS = ['mm', 'cm', 'g', 'kg', 'in', 'oz', 'lb', 'ft'];

    private function __construct() {} // @codeCoverageIgnore

    public static function format(string $key, int|float|string|bool $value): string
    {
        [$label, $unit] = self::split($key);
        $formattedValue = self::formatValue($value);

        return $unit === null ? "{$label}: {$formattedValue}" : "{$label}: {$formattedValue} {$unit}";
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private static function split(string $key): array
    {
        $segments = explode('_', $key);
        $lastSegment = $segments[count($segments) - 1];

        if (count($segments) > 1 && in_array($lastSegment, self::KNOWN_UNITS, true)) {
            array_pop($segments);

            return [self::titleCase(implode('_', $segments)), $lastSegment];
        }

        return [self::titleCase($key), null];
    }

    private static function titleCase(string $key): string
    {
        return ucwords(str_replace('_', ' ', $key));
    }

    private static function formatValue(int|float|string|bool $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }
}
