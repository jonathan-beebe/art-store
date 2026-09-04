<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

use DateTimeImmutable;
use DateTimeZone;

it('builds the flagged sentence from its numbers', function (): void {
    $peakHourStart = new DateTimeImmutable('2026-09-01 21:00:00', new DateTimeZone('UTC'));

    $text = FlaggedActorSummary::text(412, $peakHourStart, '185.220.101.42', 31, true);

    expect($text)->toBe(
        '412 listing views between 21:00 and 22:00 UTC on Sep 1 from 185.220.101.42, one every 8.7 seconds across 31 listings.',
    );
});

it('appends the no-favorite-or-cart clause when the range carried neither', function (): void {
    $peakHourStart = new DateTimeImmutable('2026-09-01 21:00:00', new DateTimeZone('UTC'));

    $text = FlaggedActorSummary::text(412, $peakHourStart, '185.220.101.42', 31, false);

    expect($text)->toBe(
        '412 listing views between 21:00 and 22:00 UTC on Sep 1 from 185.220.101.42, one every 8.7 seconds across 31 listings, no favorite or cart event in the range.',
    );
});

it('rounds the seconds-per-event rate to one decimal', function (): void {
    $peakHourStart = new DateTimeImmutable('2026-09-01 09:00:00', new DateTimeZone('UTC'));

    $text = FlaggedActorSummary::text(100, $peakHourStart, '10.0.0.1', 5, true);

    expect($text)->toContain('one every 36.0 seconds');
});
