<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * A timestamp the way an inbox row reads it: exact enough to matter within
 * the last day ("now", "5m", "3h"), a calendar landmark ("Yesterday") just
 * after, and a bare date once neither elapsed time nor "yesterday" says
 * anything useful. Pure — every case a caller sees is decided by the two
 * instants passed in, nothing else.
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
}
