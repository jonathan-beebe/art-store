<?php

declare(strict_types=1);

namespace App\Providers;

use App\Analytics\Analytics;
use App\Analytics\AnalyticsEvent;
use App\Analytics\AnalyticsEventName;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

it('binds one Analytics handle per process', function (): void {
    expect(app(Analytics::class))->toBeInstanceOf(Analytics::class)
        ->and(app(Analytics::class))->toBe(app(Analytics::class));
});

it('flushes a resolved Analytics handle after the command that used it finishes', function (): void {
    $event = AnalyticsEvent::forListing(AnalyticsEventName::ListingView, 'lst_ABC', null, new DateTimeImmutable);
    app(Analytics::class)->recordEvent($event);

    $this->app->terminate();

    expect(DB::connection('analytics')->table('analytics_events')->count())->toBe(1);
});

it('does nothing on termination when nothing ever resolved Analytics', function (): void {
    expect($this->app->resolved(Analytics::class))->toBeFalse();

    $this->app->terminate();

    expect(DB::connection('analytics')->table('analytics_events')->count())->toBe(0);
});
