<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The English ordinal word for a 1-based position — "1st", "2nd", "3rd",
 * "4th" — used wherever a seller reads a row's place in a list rather than
 * its raw index.
 */
final class Ordinal
{
    private function __construct() {} // @codeCoverageIgnore

    public static function of(int $position): string
    {
        if ($position % 100 >= 11 && $position % 100 <= 13) {
            return "{$position}th";
        }

        $suffix = match ($position % 10) {
            1 => 'st',
            2 => 'nd',
            3 => 'rd',
            default => 'th',
        };

        return "{$position}{$suffix}";
    }
}
