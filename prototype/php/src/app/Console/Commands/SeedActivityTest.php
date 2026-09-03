<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Analytics\AnalyticsEventName;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Listing;
use App\Models\Order;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\PendingCommand;
use RuntimeException;

/**
 * `$this->artisan()` hands back an exit code when console output is not
 * mocked and a pending command when it is — {@see RunWeeklyPayoutsTest}'s
 * own fixture, the same shape here.
 */
$pending = fn (PendingCommand|int $command): PendingCommand => $command instanceof PendingCommand
    ? $command
    : throw new RuntimeException('Console output is not mocked, so the command ran instead of pending.');

it('fills the app database and the analytics store with a small window of activity', function () use ($pending): void {
    $this->seed(DatabaseSeeder::class);
    $customersBefore = Customer::query()->count();

    $pending($this->artisan('seed:activity', ['--days' => 7]))
        ->assertSuccessful();

    expect(Customer::query()->count())->toBeGreaterThan($customersBefore)
        ->and(DB::connection('analytics')->table('analytics_events')->count())->toBeGreaterThan(0)
        ->and(DB::connection('analytics')->table('analytics_visits')->count())->toBeGreaterThan(0)
        ->and(DB::table('seed_runs')->count())->toBe(1);
});

it('creates and publishes at least one new listing within a seven-day window', function (): void {
    $this->seed(DatabaseSeeder::class);
    $listingsBefore = Listing::query()->count();

    Artisan::call('seed:activity', ['--days' => 7]);

    expect(Listing::query()->count())->toBeGreaterThan($listingsBefore);
});

it('opens at least one conversation within a seven-day window', function (): void {
    $this->seed(DatabaseSeeder::class);
    $conversationsBefore = Conversation::query()->count();

    Artisan::call('seed:activity', ['--days' => 7]);

    expect(Conversation::query()->count())->toBeGreaterThanOrEqual($conversationsBefore);
});

it('agrees with the app database on how many orders were placed and paid', function (): void {
    $this->seed(DatabaseSeeder::class);

    Artisan::call('seed:activity', ['--days' => 30]);

    $placedEvents = DB::connection('analytics')->table('analytics_events')
        ->where('name', AnalyticsEventName::OrderPlace->value)
        ->count();
    $paidEvents = DB::connection('analytics')->table('analytics_events')
        ->where('name', AnalyticsEventName::OrderPay->value)
        ->count();

    expect($placedEvents)->toBe(Order::query()->count())
        ->and($paidEvents)->toBe(Order::query()->whereNotNull('finalized_at')->count())
        ->and($placedEvents)->toBeGreaterThan(0);
});

it('refuses a second run against a database that already carries its marker', function () use ($pending): void {
    $this->seed(DatabaseSeeder::class);
    Artisan::call('seed:activity', ['--days' => 7]);

    $pending($this->artisan('seed:activity', ['--days' => 7]))
        ->expectsOutputToContain('already run once')
        ->assertFailed();

    expect(DB::table('seed_runs')->count())->toBe(1);
});

it('refuses to run in production', function () use ($pending): void {
    $this->seed(DatabaseSeeder::class);
    $this->app->instance('env', 'production');

    $pending($this->artisan('seed:activity', ['--days' => 7]))
        ->expectsOutputToContain('refuses to run in production')
        ->assertFailed();

    expect(DB::table('seed_runs')->count())->toBe(0);
});

it('refuses when make fresh\'s sellers and listings have not been seeded', function () use ($pending): void {
    $pending($this->artisan('seed:activity', ['--days' => 7]))
        ->expectsOutputToContain("needs make fresh's sellers and listings")
        ->assertFailed();
});
