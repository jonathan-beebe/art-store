<?php

declare(strict_types=1);

namespace App\Support;

/**
 * WCAG 2 contrast arithmetic over hex colors, for the design-system page's
 * pairing specimens. Pure functions: hex in, ratio out.
 */
final class Contrast
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * The WCAG contrast ratio between two colors, 1.0 through 21.0.
     */
    public static function ratio(string $hexA, string $hexB): float
    {
        $a = self::luminance($hexA);
        $b = self::luminance($hexB);

        [$darker, $lighter] = $a < $b ? [$a, $b] : [$b, $a];

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /**
     * Whether the ratio clears WCAG AA for normal text (4.5:1).
     */
    public static function meetsAa(float $ratio): bool
    {
        return $ratio >= 4.5;
    }

    /**
     * Whether the ratio clears WCAG AA for large text and UI components (3:1).
     */
    public static function meetsAaLarge(float $ratio): bool
    {
        return $ratio >= 3.0;
    }

    /**
     * Alpha-blends a translucent `#rrggbb` fill over an opaque `#rrggbb`
     * ground, per-channel, and returns the resulting opaque `#rrggbb`. A
     * contrast check needs this before rating a translucent fill: the
     * ratio belongs to the blended color the eye actually sees.
     */
    public static function compositeOver(string $fillHex, float $alpha, string $groundHex): string
    {
        [$fillRed, $fillGreen, $fillBlue] = self::channels($fillHex);
        [$groundRed, $groundGreen, $groundBlue] = self::channels($groundHex);

        return sprintf(
            '#%02x%02x%02x',
            (int) round($fillRed * $alpha + $groundRed * (1 - $alpha)),
            (int) round($fillGreen * $alpha + $groundGreen * (1 - $alpha)),
            (int) round($fillBlue * $alpha + $groundBlue * (1 - $alpha)),
        );
    }

    /**
     * Relative luminance of a `#rrggbb` color per WCAG 2.
     */
    private static function luminance(string $hex): float
    {
        [$red, $green, $blue] = self::channels($hex);

        return 0.2126 * self::channel($red / 255) + 0.7152 * self::channel($green / 255) + 0.0722 * self::channel($blue / 255);
    }

    /**
     * @return array{int, int, int}
     */
    private static function channels(string $hex): array
    {
        $value = ltrim($hex, '#');

        return [(int) hexdec(substr($value, 0, 2)), (int) hexdec(substr($value, 2, 2)), (int) hexdec(substr($value, 4, 2))];
    }

    private static function channel(float $value): float
    {
        return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
    }
}
