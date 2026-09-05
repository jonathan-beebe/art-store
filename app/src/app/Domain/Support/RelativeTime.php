<?php

declare(strict_types=1);

namespace App\Domain\Support;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * How long ago something happened, in two shapes.
 * {@see short()} is the way an inbox row reads a timestamp. It shows an
 * elapsed count within the last day: "now", "5m", "3h". It shows
 * "Yesterday" for the day just before today. It falls back to a bare
 * date once elapsed time and "yesterday" both stop applying.
 * {@see long()} spells out the same span in words: "2 days ago". A full
 * word reads more clearly in a sentence than an abbreviation does.
 * The method is pure: the two instants passed in decide every case a
 * caller sees. This is why it sits in the core beside the objects that
 * read it.
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
