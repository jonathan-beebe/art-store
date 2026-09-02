<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

use DateTimeImmutable;
use DateTimeZone;

it('names the sizes the range picker offers', function (): void {
    expect(AnalyticsRange::SIZES)->toBe([7, 30, 90]);
});

it('bounds a window to whole UTC days ending on the given moment\'s day', function (): void {
    $range = AnalyticsRange::of(30, new DateTimeImmutable('2026-09-02T14:30:00+00:00'));

    expect($range->days)->toBe(30)
        ->and($range->start->format('Y-m-d H:i:s'))->toBe('2026-08-04 00:00:00')
        ->and($range->end->format('Y-m-d H:i:s'))->toBe('2026-09-02 23:59:59');
});

it('floors a moment carrying another timezone to its UTC day', function (): void {
    $range = AnalyticsRange::of(7, new DateTimeImmutable('2026-09-03T01:30:00', new DateTimeZone('-02:00')));

    // 2026-09-03T01:30-02:00 is 2026-09-03T03:30 UTC, so the day is the 3rd.
    expect($range->end->format('Y-m-d H:i:s'))->toBe('2026-09-03 23:59:59');
});

it('is the same number of days immediately before it, with no gap and no overlap', function (): void {
    $range = AnalyticsRange::of(30, new DateTimeImmutable('2026-09-02T14:30:00+00:00'));
    $previous = $range->previous();

    expect($previous->days)->toBe(30)
        ->and($previous->start->format('Y-m-d H:i:s'))->toBe('2026-07-05 00:00:00')
        ->and($previous->end->format('Y-m-d H:i:s'))->toBe('2026-08-03 23:59:59');
});

it('captions the range against the one before it', function (): void {
    $range = AnalyticsRange::of(30, new DateTimeImmutable('2026-09-02T14:30:00+00:00'));

    expect($range->caption())->toBe('Aug 4 – Sep 2 vs Jul 5 – Aug 3');
});

it('lists every day in the range, oldest first', function (): void {
    $range = AnalyticsRange::of(7, new DateTimeImmutable('2026-09-02T14:30:00+00:00'));

    expect($range->dayLabels())->toBe([
        '2026-08-27', '2026-08-28', '2026-08-29', '2026-08-30', '2026-08-31', '2026-09-01', '2026-09-02',
    ]);
});

it('reads a day label back as a short caption', function (): void {
    expect(AnalyticsRange::dayCaption('2026-09-01'))->toBe('Sep 1');
});
