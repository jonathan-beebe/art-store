<?php

declare(strict_types=1);

namespace App\Console\Commands\Sweep;

use App\Analytics\Analytics;
use App\Analytics\AnalyticsEvent;
use App\Analytics\AnalyticsVisit;
use App\Domain\Analytics\AnalyticsEventName;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\PendingCommand;
use RuntimeException;

/**
 * `$this->artisan()` hands back an exit code when console output is not mocked
 * and a pending command when it is. These tests assert on output, so they run
 * the command through the pending command.
 */
$pending = fn (PendingCommand|int $command): PendingCommand => $command instanceof PendingCommand
    ? $command
    : throw new RuntimeException('Console output is not mocked, so the command ran instead of pending.');

it('fails clearly on a garbage --as-of value', function () use ($pending): void {
    $pending($this->artisan('sweep:analytics', ['--as-of' => 'yesterdayish']))
        ->expectsOutputToContain('is not a date the sweep can run as of')
        ->assertFailed();
});

it('prunes analytics events older than ANALYTICS_RETENTION_DAYS, as of the sweep date', function () use ($pending): void {
    config(['analytics.retention_days' => 14]);
    $analytics = app(Analytics::class);
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, 'lst_ABC', null, new DateTimeImmutable('2026-07-01T00:00:00+00:00')));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, 'lst_ABC', null, new DateTimeImmutable('2026-08-20T00:00:00+00:00')));
    $analytics->flush();

    $pending($this->artisan('sweep:analytics', ['--as-of' => '2026-08-24']))
        ->expectsOutputToContain('1 analytics row(s) pruned.')
        ->assertSuccessful();

    $rows = DB::connection('analytics')->table('analytics_events')->get();

    expect($rows)->toHaveCount(1)
        ->and($rows->sole()->occurred_at)->toBe('2026-08-20 00:00:00');
});

it('prunes analytics visits too, folded into the same printed count', function () use ($pending): void {
    config(['analytics.retention_days' => 14]);
    $analytics = app(Analytics::class);
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, 'lst_ABC', null, new DateTimeImmutable('2026-07-01T00:00:00+00:00')));
    $analytics->recordVisit(new AnalyticsVisit('ses_old', new DateTimeImmutable('2026-07-01T00:00:00+00:00'), '/', null, null, null, null, null, null, null));
    $analytics->recordVisit(new AnalyticsVisit('ses_new', new DateTimeImmutable('2026-08-20T00:00:00+00:00'), '/', null, null, null, null, null, null, null));
    $analytics->flush();

    $pending($this->artisan('sweep:analytics', ['--as-of' => '2026-08-24']))
        ->expectsOutputToContain('2 analytics row(s) pruned.')
        ->assertSuccessful();

    $rows = DB::connection('analytics')->table('analytics_visits')->get();

    expect($rows)->toHaveCount(1)
        ->and($rows->sole()->session_id)->toBe('ses_new');
});

it('skips the analytics retention prune silently when ANALYTICS_RETENTION_DAYS is off', function () use ($pending): void {
    config(['analytics.retention_days' => null]);
    $analytics = app(Analytics::class);
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, 'lst_ABC', null, new DateTimeImmutable('2020-01-01T00:00:00+00:00')));
    $analytics->recordVisit(new AnalyticsVisit('ses_old', new DateTimeImmutable('2020-01-01T00:00:00+00:00'), '/', null, null, null, null, null, null, null));
    $analytics->flush();

    $pending($this->artisan('sweep:analytics'))
        ->assertSuccessful();

    expect(DB::connection('analytics')->table('analytics_events')->count())->toBe(1)
        ->and(DB::connection('analytics')->table('analytics_visits')->count())->toBe(1);
});

it('fails the command on an analytics prune failure', function () use ($pending): void {
    config(['analytics.retention_days' => 14]);
    DB::connection('analytics')->getPdo()->exec('DROP TABLE analytics_events');

    $pending($this->artisan('sweep:analytics'))
        ->expectsOutputToContain('analytics retention prune failed')
        ->assertFailed();
});
