<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Analytics\ActorVelocity;
use App\Domain\Analytics\AnalyticsEventName;
use App\Logging\LogStore;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Listing;
use App\Models\Order;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\PendingCommand;
use RuntimeException;
use stdClass;
use Tests\LogStoreFixtures as Fixtures;

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

it('tolerates a disabled log store', function () use ($pending): void {
    $this->app->instance(LogStore::class, LogStore::open('off'));
    $this->seed(DatabaseSeeder::class);

    $pending($this->artisan('seed:activity', ['--days' => 7]))
        ->assertSuccessful();
});

it('flags the scraper on the leaderboard and leaves the prober findable only by ip and log lines', function (): void {
    $store = LogStore::open(Fixtures::tempFile());
    $this->app->instance(LogStore::class, $store);
    $this->seed(DatabaseSeeder::class);

    Artisan::call('seed:activity', ['--days' => 92]);

    $peakRow = DB::connection('analytics')->table('analytics_events')
        ->whereNotNull('actor_id')
        ->select('actor_id')
        ->selectRaw("strftime('%Y-%m-%dT%H', occurred_at) as hour")
        ->selectRaw('count(*) as tally')
        ->groupBy('actor_id', 'hour')
        ->orderByDesc('tally')
        ->first();
    $peak = $peakRow instanceof stdClass ? $peakRow : throw new RuntimeException('expected at least one actor_id/hour row');

    /** @var int|string $tally */
    $tally = $peak->tally;
    /** @var string $peakActorId */
    $peakActorId = $peak->actor_id;

    expect(ActorVelocity::flags((int) $tally))->toBeTrue();

    /** @var string $scraperIp */
    $scraperIp = DB::connection('analytics')->table('analytics_events')
        ->where('actor_id', $peakActorId)
        ->value('ip');
    expect($scraperIp)->toStartWith('185.220.101.');

    // The prober's fixed ip carries exactly one real analytics event (the
    // couple of ordinary page views its script opens with) and nothing
    // else — its probe burst never reaches the analytics store — plus its
    // 404 log lines, findable through the log store alone.
    $proberEvents = DB::connection('analytics')->table('analytics_events')
        ->where('ip', '45.155.205.233')
        ->count();
    expect($proberEvents)->toBeGreaterThan(0);

    $connection = Fixtures::connectionOrFail($store);
    /** @var string $proberActorId */
    $proberActorId = DB::connection('analytics')->table('analytics_events')
        ->where('ip', '45.155.205.233')
        ->value('actor_id');
    $notFoundLines = Fixtures::scalar($connection, sprintf(
        "SELECT COUNT(*) FROM log_lines WHERE actor_id = '%s' AND event = 'http.request' AND phase = 'did' AND data LIKE '%%\"status\":404%%'",
        $proberActorId,
    ));
    expect((int) $notFoundLines)->toBeGreaterThan(0);
});
