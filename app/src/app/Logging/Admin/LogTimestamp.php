<?php

declare(strict_types=1);

namespace App\Logging\Admin;

/**
 * A row's clock face: `ts` is a fixed-width ISO-8601 UTC instant
 * (`docs/spec.md` §1), and every columnar row shows only its
 * `HH:MM:SS.mmm` time-of-day — the date and the `T`/`Z` markers are dead
 * weight once the list is already "newest first · today · UTC" — while the
 * full instant stays reachable in the cell's `title`.
 */
final class LogTimestamp
{
    private function __construct() {} // @codeCoverageIgnore

    private const int TIME_OFFSET = 11;

    private const int TIME_LENGTH = 12;

    /** A `ts` shorter than the fixed shape (the mirror invariant admits
     * one) renders as given, not a mangled slice of it. */
    public static function timeOfDay(string $ts): string
    {
        return strlen($ts) >= self::TIME_OFFSET + self::TIME_LENGTH
            ? substr($ts, self::TIME_OFFSET, self::TIME_LENGTH)
            : $ts;
    }
}
