<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

it('schedules the weekly payout for the Monday after the period closes', function (): void {
    $runs = array_values(array_filter(
        app(Schedule::class)->events(),
        fn (Event $event): bool => str_contains((string) $event->command, 'payouts:run'),
    ));

    expect($runs)->toHaveCount(1)
        ->and($runs[0]->expression)->toBe('0 2 * * 1');
});

it('schedules the stale-order sweep every hour', function (): void {
    $runs = array_values(array_filter(
        app(Schedule::class)->events(),
        fn (Event $event): bool => str_contains((string) $event->command, 'sweep:orders'),
    ));

    expect($runs)->toHaveCount(1)
        ->and($runs[0]->expression)->toBe('0 * * * *');
});

it('schedules the log retention sweep daily', function (): void {
    $runs = array_values(array_filter(
        app(Schedule::class)->events(),
        fn (Event $event): bool => str_contains((string) $event->command, 'sweep:logs'),
    ));

    expect($runs)->toHaveCount(1)
        ->and($runs[0]->expression)->toBe('0 3 * * *');
});

it('schedules the analytics retention sweep daily', function (): void {
    $runs = array_values(array_filter(
        app(Schedule::class)->events(),
        fn (Event $event): bool => str_contains((string) $event->command, 'sweep:analytics'),
    ));

    expect($runs)->toHaveCount(1)
        ->and($runs[0]->expression)->toBe('0 3 * * *');
});
