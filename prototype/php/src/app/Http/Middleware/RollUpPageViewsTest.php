<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Analytics\PageViewSite;
use App\Models\PageViewCount;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\CapturedStory;

it('rolls a countable GET up into one row', function (): void {
    $this->get('/admin/login');

    $row = PageViewCount::query()->sole();

    expect($row->site)->toBe(PageViewSite::Admin->value)
        ->and($row->path_pattern)->toBe('/admin/login')
        ->and($row->day)->toBe(now()->format('Y-m-d'))
        ->and($row->count)->toBe(1);
});

it('reads the storefront root as its own pattern', function (): void {
    $this->get('/');

    $row = PageViewCount::query()->sole();

    expect($row->site)->toBe(PageViewSite::Shop->value)
        ->and($row->path_pattern)->toBe('/');
});

it('increments the same row on a second hit rather than inserting a new one', function (): void {
    $this->get('/admin/login');
    $this->get('/admin/login');

    $row = PageViewCount::query()->sole();

    expect($row->count)->toBe(2);
});

it('counts nothing for a request that matches no route', function (): void {
    $this->get('/nothing-is-here')->assertNotFound();

    expect(PageViewCount::query()->count())->toBe(0);
});

it('counts nothing for a non-GET request', function (): void {
    $this->post('/admin/login', ['email' => 'not-an-email']);

    expect(PageViewCount::query()->count())->toBe(0);
});

it('counts nothing for a response that is not 2xx', function (): void {
    $this->get('/seller')->assertRedirect();

    expect(PageViewCount::query()->count())->toBe(0);
});

it('counts nothing for a response that is not HTML', function (): void {
    Route::get('/json-test', fn () => response()->json(['ok' => true]));

    $this->getJson('/json-test')->assertOk();

    expect(PageViewCount::query()->count())->toBe(0);
});

it('writes the roll-up through the analytics connection, never the default one', function (): void {
    /** @var list<QueryExecuted> $queries */
    $queries = [];

    DB::connection()->listen(function (QueryExecuted $query) use (&$queries): void {
        $queries[] = $query;
    });

    $this->get('/admin/login');

    $mentioning = fn (string $connection): array => array_values(array_filter(
        $queries,
        fn (QueryExecuted $query): bool => $query->connectionName === $connection && str_contains($query->sql, 'page_view_counts'),
    ));

    expect($mentioning('sqlite'))->toBe([])
        ->and($mentioning('analytics'))->not->toBe([]);
});

it('still answers and logs a warning when the analytics connection cannot be written to', function (): void {
    $log = CapturedStory::capture();
    $originalDatabase = config('database.connections.analytics.database');
    // RefreshDatabase already opened a transaction on this PDO for the
    // current test (tests/TestCase.php's connectionsToTransact); purging
    // the connection below drops the wrapper without closing it, so it is
    // rolled back by hand once the test is done with it — otherwise the
    // next test to begin a transaction on the same cached in-memory PDO
    // finds one already open.
    $originalPdo = DB::connection('analytics')->getPdo();

    config()->set('database.connections.analytics.database', '/nonexistent/dir/analytics.sqlite3');
    DB::purge('analytics');

    try {
        $this->get('/admin/login')->assertOk();

        expect($log->line('app.log', 'doing')['level'])->toBe('warn');
    } finally {
        if ($originalPdo->inTransaction()) {
            $originalPdo->rollBack();
        }

        config()->set('database.connections.analytics.database', $originalDatabase);
        DB::purge('analytics');
    }
});
