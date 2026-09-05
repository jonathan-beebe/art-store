<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// A payout period is Monday to Sunday, so the run that settles one happens
// early on the Monday after it closes.
Schedule::command('payouts:run')->weeklyOn(1, '02:00');

// An abandoned checkout holds its stock off the storefront until the sweep
// takes it back, so the sweep runs often enough that the wait past the
// cutoff stays in minutes.
Schedule::command('sweep:orders')->hourly();

// Both retention windows are measured in days, so one daily run per store
// prunes ahead of either window closing.
Schedule::command('sweep:logs')->dailyAt('03:00');
Schedule::command('sweep:analytics')->dailyAt('03:00');

// Runs after the other daily sweeps, since an anonymous customer's own
// history — a favorite, an order, a conversation — is what keeps it out of
// this one's reach.
Schedule::command('sweep:customers')->dailyAt('03:30');
