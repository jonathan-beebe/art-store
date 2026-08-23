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
