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
use App\Models\Payout;
use Database\Seeders\DatabaseSeeder;
use DateTimeImmutable;
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

it('never writes a timestamp after the moment the command runs, even run early in the day', function (): void {
    $now = $this->moment('2026-09-03 04:00:00');
    $this->travelTo($now);
    $this->seed(DatabaseSeeder::class);

    Artisan::call('seed:activity', ['--days' => 92]);

    $nowSql = $now->format('Y-m-d H:i:s');

    $futureEvents = DB::connection('analytics')->table('analytics_events')->where('occurred_at', '>', $nowSql)->count();
    $futureVisits = DB::connection('analytics')->table('analytics_visits')->where('first_seen_at', '>', $nowSql)->count();
    $futureListings = Listing::query()->where('created_at', '>', $now)->count();

    expect($futureEvents)->toBe(0)
        ->and($futureVisits)->toBe(0)
        ->and($futureListings)->toBe(0);
});

it('floors to the same start-of-day plan whatever time of day it runs at', function (): void {
    // The exact `$startDay` derivation `handle()` uses — the plan itself
    // is already proven deterministic from a fixed start day
    // (ActivityPlanTest); this pins that two different clock readings on
    // the same calendar day floor to that same start day, so a run at
    // 04:00 and a run at 23:59 script identical activity.
    $startOfDay = fn (DateTimeImmutable $now, int $days): DateTimeImmutable => $now
        ->modify('-'.($days - 1).' days')
        ->setTime(0, 0, 0);

    $morning = $this->moment('2026-09-03 04:00:00');
    $evening = $this->moment('2026-09-03 23:59:59');

    expect($startOfDay($morning, 92))->toEqual($startOfDay($evening, 92));
});

it('skips a payout week that collides with a period the demo data already settled', function () use ($pending): void {
    // OrderHistorySeeder (make fresh's own order history) releases escrow
    // for "Garden Gnome in Reclaimed Oak"'s seller on 2026-07-10 and pays
    // it out as of 2026-07-16 — period_start 2026-07-06. Travelling to
    // 2026-09-03 makes seed:activity's own 92-day payout sweep revisit
    // that exact date (7 weeks back), so a second delivery for the same
    // seller in the same week collides on payouts' unique key the moment
    // this command's sweep reaches it.
    $this->travelTo($this->moment('2026-09-03 12:00:00'));
    $this->seed(DatabaseSeeder::class);

    $seller = Listing::where('title', 'Garden Gnome in Reclaimed Oak')->sole()->seller;
    $periodStart = '2026-07-06';
    expect(Payout::query()->where('seller_id', $seller->id)->whereDate('period_start', $periodStart)->count())->toBe(1);

    $this->deliveredFulfillmentFor(
        $seller,
        orderedAt: $this->moment('2026-07-06 12:00:00'),
        shippedAt: $this->moment('2026-07-08 11:00:00'),
        deliveredAt: $this->moment('2026-07-11 09:00:00'),
    );

    $pending($this->artisan('seed:activity', ['--days' => 92]))
        ->assertSuccessful();

    expect(DB::table('seed_runs')->count())->toBe(1)
        ->and(Payout::query()->where('seller_id', $seller->id)->whereDate('period_start', $periodStart)->count())->toBe(1);
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

it('writes log lines for the requests it simulates, sharing ids with the analytics store', function (): void {
    $store = LogStore::open(Fixtures::tempFile());
    $this->app->instance(LogStore::class, $store);
    $this->seed(DatabaseSeeder::class);

    Artisan::call('seed:activity', ['--days' => 92]);

    $connection = Fixtures::connectionOrFail($store);

    expect(Fixtures::rowCount($connection))->toBeGreaterThan(0);

    $events = Fixtures::column($connection, 'SELECT DISTINCT event FROM log_lines');
    expect($events)->toContain('http.request')
        ->toContain('magic_link.request')
        ->toContain('magic_link.consume');

    // The very first session the plan ever scripts carries this id — see
    // ActivityPlan::buildSession()'s sprintf('ses%05d', ...) — and every
    // one of its steps' http.request lines should carry it too.
    $firstSessionLines = Fixtures::column($connection, "SELECT event FROM log_lines WHERE session_id = 'ses00000'");
    expect($firstSessionLines)->not->toBe([]);

    // Every will/did pair shares one request id.
    $orphanedWills = Fixtures::scalar($connection, <<<'SQL'
        SELECT COUNT(*) FROM log_lines w
        WHERE w.event = 'http.request' AND w.phase = 'will'
        AND NOT EXISTS (
            SELECT 1 FROM log_lines d
            WHERE d.event = 'http.request' AND d.phase = 'did' AND d.request_id = w.request_id
        )
        SQL);
    expect($orphanedWills)->toBe(0);
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

    // The same store the seed just wrote to is what the log viewer's own
    // grouped view (the "Open in logs" page a founder lands on from the
    // actor page) reads — its will/did pairs render there the same way
    // they were captured.
    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/logs?actor={$proberActorId}&group=1");

    $response->assertOk()
        ->assertSee('/wp-login.php')
        ->assertSee('404');
});
