<?php

declare(strict_types=1);

namespace App\Support;

/**
 * WCAG 2 contrast arithmetic over hex colors, for the design-system page's
 * pairing specimens. Pure functions: hex in, ratio out.
 */
final class Contrast
{
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
     * Relative luminance of a `#rrggbb` color per WCAG 2.
     */
    private static function luminance(string $hex): float
    {
        $value = ltrim($hex, '#');

        $red = self::channel(hexdec(substr($value, 0, 2)) / 255);
        $green = self::channel(hexdec(substr($value, 2, 2)) / 255);
        $blue = self::channel(hexdec(substr($value, 4, 2)) / 255);

        return 0.2126 * $red + 0.7152 * $green + 0.0722 * $blue;
    }

    private static function channel(float $value): float
    {
        return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
    }
}
