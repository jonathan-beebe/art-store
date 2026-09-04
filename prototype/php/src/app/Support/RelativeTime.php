<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * How long ago something happened, in two shapes. {@see short()} is the
 * way an inbox row reads a timestamp: exact enough to matter within the
 * last day ("now", "5m", "3h"), a calendar landmark ("Yesterday") just
 * after, and a bare date once neither elapsed time nor "yesterday" says
 * anything useful. {@see long()} is the same span spelled out ("2 days
 * ago"), which a sentence reads better than an abbreviation. Pure — every
 * case a caller sees is decided by the two instants passed in, nothing
 * else.
 */
final class RelativeTime
{
    private function __construct() {} // @codeCoverageIgnore

    public static function short(DateTimeInterface $at, DateTimeInterface $now): string
    {
        $elapsedSeconds = $now->getTimestamp() - $at->getTimestamp();

        if ($elapsedSeconds < 60) {
            return 'now';
        }

        $elapsedMinutes = intdiv($elapsedSeconds, 60);

        if ($elapsedMinutes < 60) {
            return "{$elapsedMinutes}m";
        }

        $elapsedHours = intdiv($elapsedMinutes, 60);

        if ($elapsedHours < 24) {
            return "{$elapsedHours}h";
        }

        $at = DateTimeImmutable::createFromInterface($at);
        $now = DateTimeImmutable::createFromInterface($now);

        if ($at->format('Y-m-d') === $now->modify('-1 day')->format('Y-m-d')) {
            return 'Yesterday';
        }

        return $at->format('Y') === $now->format('Y') ? $at->format('M j') : $at->format('M j, Y');
    }

    /**
     * The span spelled out, in the largest unit that still says something:
     * "just now", "5 minutes ago", "3 hours ago", "2 days ago".
     */
    public static function long(DateTimeInterface $at, DateTimeInterface $now): string
    {
        $minutes = intdiv($now->getTimestamp() - $at->getTimestamp(), 60);

        if ($minutes < 1) {
            return 'just now';
        }

        if ($minutes < 60) {
            return self::plural($minutes, 'minute');
        }

        $hours = intdiv($minutes, 60);

        return $hours < 24 ? self::plural($hours, 'hour') : self::plural(intdiv($hours, 24), 'day');
    }

    private static function plural(int $count, string $unit): string
    {
        return $count === 1 ? "1 {$unit} ago" : "{$count} {$unit}s ago";
    }
}
