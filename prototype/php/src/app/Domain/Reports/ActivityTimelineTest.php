<?php

declare(strict_types=1);

namespace App\Domain\Reports;

use App\Analytics\AnalyticsEventName;
use DateTimeImmutable;
use InvalidArgumentException;

it('returns one row per day, oldest first', function (): void {
    $days = ActivityTimeline::lastDays([], new DateTimeImmutable('2026-08-22 17:30:00'), 3);

    expect($days)->toHaveCount(3)
        ->and(array_map(fn (DailyActivity $day): string => $day->date->format('Y-m-d'), $days))
        ->toBe(['2026-08-20', '2026-08-21', '2026-08-22']);
});

it('reads counts by date and event type', function (): void {
    $counts = [
        '2026-08-21' => [
            AnalyticsEventName::ListingView->value => 4,
            AnalyticsEventName::ListingFavorite->value => 1,
            AnalyticsEventName::ListingCartAdd->value => 2,
        ],
    ];

    $days = ActivityTimeline::lastDays($counts, new DateTimeImmutable('2026-08-22 09:00:00'), 2);

    expect($days[0]->views)->toBe(4)
        ->and($days[0]->favorites)->toBe(1)
        ->and($days[0]->cartAdds)->toBe(2);
});

it('fills days with no events with zeroes', function (): void {
    $counts = ['2026-08-22' => [AnalyticsEventName::ListingView->value => 7]];

    $days = ActivityTimeline::lastDays($counts, new DateTimeImmutable('2026-08-22 09:00:00'), 2);

    expect($days[0]->views)->toBe(0)
        ->and($days[0]->favorites)->toBe(0)
        ->and($days[1]->views)->toBe(7);
});

it('ignores counts outside the window', function (): void {
    $counts = ['2026-07-01' => [AnalyticsEventName::ListingView->value => 99]];

    $days = ActivityTimeline::lastDays($counts, new DateTimeImmutable('2026-08-22 09:00:00'), 2);

    expect(array_sum(array_map(fn (DailyActivity $day): int => $day->total(), $days)))->toBe(0);
});

it('ignores event types the report does not show', function (): void {
    $counts = ['2026-08-22' => [AnalyticsEventName::ListingUnfavorite->value => 5]];

    $days = ActivityTimeline::lastDays($counts, new DateTimeImmutable('2026-08-22 09:00:00'), 1);

    expect($days[0]->total())->toBe(0);
});

it('rejects a window shorter than a day', function (): void {
    expect(fn () => ActivityTimeline::lastDays([], new DateTimeImmutable('2026-08-22 09:00:00'), 0))
        ->toThrow(InvalidArgumentException::class);
});

it('opens the window at midnight on the oldest day it covers', function (): void {
    $first = ActivityTimeline::firstDay(new DateTimeImmutable('2026-08-22 17:30:00'), 14);

    expect($first->format('Y-m-d H:i:s'))->toBe('2026-08-09 00:00:00');
});

it('rejects a window shorter than a day when naming its first day', function (): void {
    expect(fn () => ActivityTimeline::firstDay(new DateTimeImmutable('2026-08-22 09:00:00'), 0))
        ->toThrow(InvalidArgumentException::class);
});
