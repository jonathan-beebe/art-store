<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;

it('reads a sub-minute gap as now', function (): void {
    $now = new DateTimeImmutable('2026-08-21 12:00:00');

    expect(RelativeTime::short($now->modify('-30 seconds'), $now))->toBe('now')
        ->and(RelativeTime::short($now, $now))->toBe('now');
});

it('reads whole minutes under an hour as Nm', function (): void {
    $now = new DateTimeImmutable('2026-08-21 12:00:00');

    expect(RelativeTime::short($now->modify('-1 minute'), $now))->toBe('1m')
        ->and(RelativeTime::short($now->modify('-59 minutes'), $now))->toBe('59m');
});

it('reads whole hours under a day as Nh', function (): void {
    $now = new DateTimeImmutable('2026-08-21 12:00:00');

    expect(RelativeTime::short($now->modify('-1 hour'), $now))->toBe('1h')
        ->and(RelativeTime::short($now->modify('-23 hours'), $now))->toBe('23h');
});

it('names yesterday by the calendar once the gap passes a day', function (): void {
    $now = new DateTimeImmutable('2026-08-21 08:00:00');
    $yesterdayEarly = new DateTimeImmutable('2026-08-20 00:30:00');

    expect(RelativeTime::short($yesterdayEarly, $now))->toBe('Yesterday');
});

it('falls back to a bare date once the gap is neither today, an hour count, nor yesterday', function (): void {
    $now = new DateTimeImmutable('2026-08-21 08:00:00');

    expect(RelativeTime::short(new DateTimeImmutable('2026-08-19 08:00:00'), $now))->toBe('Aug 19')
        ->and(RelativeTime::short(new DateTimeImmutable('2025-12-01 08:00:00'), $now))->toBe('Dec 1, 2025');
});

it('spells the span out in the largest unit that still says something', function (string $ago, string $expected): void {
    $now = new DateTimeImmutable('2026-08-21 12:00:00');

    expect(RelativeTime::long($now->modify($ago), $now))->toBe($expected);
})->with([
    'under a minute' => ['-30 seconds', 'just now'],
    'one minute' => ['-1 minute', '1 minute ago'],
    'minutes' => ['-45 minutes', '45 minutes ago'],
    'one hour' => ['-1 hour', '1 hour ago'],
    'hours' => ['-23 hours', '23 hours ago'],
    'one day' => ['-24 hours', '1 day ago'],
    'days' => ['-9 days', '9 days ago'],
]);
