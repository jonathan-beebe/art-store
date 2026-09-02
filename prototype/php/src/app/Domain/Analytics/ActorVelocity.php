<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

/**
 * Whether an actor's busiest UTC hour in a range reads as a scripted or
 * abusive visitor rather than a person browsing — one threshold shared by
 * the leaderboard and an actor's own page, so the two never disagree about
 * who is flagged.
 */
final class ActorVelocity
{
    /** Events in a single UTC hour past which an actor is flagged. */
    public const int THRESHOLD_PER_HOUR = 100;

    private function __construct() {} // @codeCoverageIgnore

    public static function flags(int $peakPerHour): bool
    {
        return $peakPerHour >= self::THRESHOLD_PER_HOUR;
    }
}
