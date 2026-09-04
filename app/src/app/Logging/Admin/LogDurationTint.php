<?php

declare(strict_types=1);

namespace App\Logging\Admin;

/**
 * `/admin/logs`'s duration tint, the scanning aid `LogSeverity::rowClasses()`
 * gives a whole row sized for one `duration_ms` figure instead: green at or
 * under 300ms, orange through 600ms, red past it. Server-selected rather
 * than a Blade conditional, so the thresholds live in one place with their
 * own test.
 */
enum LogDurationTint
{
    case Fast;
    case Slow;
    case Bad;

    private const int FAST_MAX_MS = 300;

    private const int SLOW_MAX_MS = 600;

    /** No duration tints nothing — a line with no `duration_ms` (most of
     * them; only a root `http.request` close and a handful of others carry
     * one) renders a bare dash instead. */
    public static function ofMs(?int $durationMs): ?self
    {
        if ($durationMs === null) {
            return null;
        }

        return match (true) {
            $durationMs <= self::FAST_MAX_MS => self::Fast,
            $durationMs <= self::SLOW_MAX_MS => self::Slow,
            default => self::Bad,
        };
    }

    public function textClasses(): string
    {
        return match ($this) {
            self::Fast => 'text-green-700 dark:text-green-400',
            self::Slow => 'text-orange-700 dark:text-orange-400',
            self::Bad => 'text-red-700 dark:text-red-500 font-semibold',
        };
    }
}
