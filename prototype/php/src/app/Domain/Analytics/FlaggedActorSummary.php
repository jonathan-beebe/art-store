<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

use DateTimeImmutable;

/**
 * The sentence an actor's page shows under its warn banner once
 * {@see ActorVelocity} has flagged it — built entirely from numbers
 * {@see \App\Analytics\Admin\EntityActivity} already read, so this class
 * never queries anything itself: "412 listing views between 21:00 and
 * 22:00 UTC on Sep 1 from 185.220.101.42, one every 8.7 seconds across 31
 * listings, no favorite or cart event in the range."
 */
final class FlaggedActorSummary
{
    private function __construct() {} // @codeCoverageIgnore

    public static function text(
        int $peakCount,
        DateTimeImmutable $peakHourStart,
        string $ip,
        int $distinctListings,
        bool $hadFavoriteOrCartEvent,
    ): string {
        $hourEnd = $peakHourStart->modify('+1 hour');
        $secondsPerEvent = $peakCount > 0 ? 3600 / $peakCount : 0.0;

        $sentence = sprintf(
            '%s listing views between %s and %s UTC on %s from %s, one every %s seconds across %s listings',
            number_format($peakCount),
            $peakHourStart->format('H:i'),
            $hourEnd->format('H:i'),
            $peakHourStart->format('M j'),
            $ip,
            number_format($secondsPerEvent, 1),
            number_format($distinctListings),
        );

        return $hadFavoriteOrCartEvent
            ? $sentence.'.'
            : $sentence.', no favorite or cart event in the range.';
    }
}
