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
