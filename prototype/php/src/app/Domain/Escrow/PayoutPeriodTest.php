<?php

declare(strict_types=1);

namespace App\Domain\Escrow;

use DateTimeImmutable;

it('resolves the week ending before a given moment', function (string $moment, string $start, string $end): void {
    $period = PayoutPeriod::endingBefore(new DateTimeImmutable($moment));

    expect($period->start->format('Y-m-d H:i:s'))->toBe($start)
        ->and($period->end->format('Y-m-d H:i:s'))->toBe($end);
})->with([
    'mid-week pays out the week that just ended' => ['2026-08-22 09:30:00', '2026-08-10 00:00:00', '2026-08-16 23:59:59'],
    'the first moment of a monday pays out the week before it' => ['2026-08-17 00:00:00', '2026-08-10 00:00:00', '2026-08-16 23:59:59'],
    'the last moment of a sunday still pays out the week before it' => ['2026-08-16 23:59:59', '2026-08-03 00:00:00', '2026-08-09 23:59:59'],
    'a week spanning the new year' => ['2027-01-06 09:30:00', '2026-12-28 00:00:00', '2027-01-03 23:59:59'],
]);

it('covers seven days', function (): void {
    $period = PayoutPeriod::endingBefore(new DateTimeImmutable('2026-08-22 09:30:00'));

    expect($period->contains(new DateTimeImmutable('2026-08-10 00:00:00')))->toBeTrue()
        ->and($period->contains(new DateTimeImmutable('2026-08-16 23:59:59')))->toBeTrue()
        ->and($period->contains(new DateTimeImmutable('2026-08-09 23:59:59')))->toBeFalse()
        ->and($period->contains(new DateTimeImmutable('2026-08-17 00:00:00')))->toBeFalse();
});

it('labels itself by its first and last day', function (): void {
    $period = PayoutPeriod::endingBefore(new DateTimeImmutable('2026-08-22 09:30:00'));

    expect($period->label())->toBe('2026-08-10 to 2026-08-16');
});

it('resolves the week a moment falls in, not the week before it', function (string $moment, string $start, string $end): void {
    $period = PayoutPeriod::containing(new DateTimeImmutable($moment));

    expect($period->start->format('Y-m-d H:i:s'))->toBe($start)
        ->and($period->end->format('Y-m-d H:i:s'))->toBe($end);
})->with([
    'mid-week sits inside the week in progress' => ['2026-08-22 09:30:00', '2026-08-17 00:00:00', '2026-08-23 23:59:59'],
    'the first moment of a monday starts its own week' => ['2026-08-17 00:00:00', '2026-08-17 00:00:00', '2026-08-23 23:59:59'],
    'the last moment of a sunday still falls in that week' => ['2026-08-23 23:59:59', '2026-08-17 00:00:00', '2026-08-23 23:59:59'],
]);

it('steps to the week immediately before this one', function (): void {
    $period = PayoutPeriod::endingBefore(new DateTimeImmutable('2026-08-22 09:30:00'));

    $previous = $period->previous();

    expect($previous->start->format('Y-m-d'))->toBe('2026-08-03')
        ->and($previous->end->format('Y-m-d'))->toBe('2026-08-09');
});
