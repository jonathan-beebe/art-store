<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Analytics\PageViewSite;
use App\Models\PageViewCount;
use DateTimeImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use stdClass;
use Tests\AnalyticsStoreFixtures;
use Tests\CapturedStory;

/**
 * @return Collection<int, stdClass>
 */
function analyticsVisits(): Collection
{
    return DB::connection('analytics')->table('analytics_visits')->get();
}

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

it("records the first storefront visit's landing path, referrer, and campaign", function (): void {
    $now = new DateTimeImmutable('2026-09-02T10:00:00+00:00');
    $this->travelTo($now);

    $this->get('/?utm_source=newsletter&utm_medium=email&utm_campaign=sept', ['Referer' => 'https://google.com/search']);

    $row = analyticsVisits()->sole();

    expect($row->landing_path)->toBe('/')
        ->and($row->referrer_host)->toBe('google.com')
        ->and($row->utm_source)->toBe('newsletter')
        ->and($row->utm_medium)->toBe('email')
        ->and($row->utm_campaign)->toBe('sept')
        ->and($row->first_seen_at)->toBe('2026-09-02 10:00:00');
});

it('changes nothing on a second request from the same session carrying different utm values', function (): void {
    $this->get('/?utm_source=newsletter&utm_medium=email&utm_campaign=sept', ['Referer' => 'https://google.com/search']);
    /** @var string $sessionId */
    $sessionId = analyticsVisits()->sole()->session_id;

    $this->withCookie(NameRequestVisitor::SESSION_COOKIE, $sessionId)
        ->get('/?utm_source=ads&utm_medium=cpc&utm_campaign=fall');

    $row = analyticsVisits()->sole();

    expect($row->utm_source)->toBe('newsletter')
        ->and($row->utm_campaign)->toBe('sept');
});

it('stores no referrer host for a same-host referrer', function (): void {
    $this->get('/', ['Referer' => 'http://localhost/from-page']);

    expect(analyticsVisits()->sole()->referrer_host)->toBeNull();
});

it('records no visit for an admin page', function (): void {
    $this->get('/admin/login');

    expect(analyticsVisits())->toHaveCount(0);
});

it('records a visit for a first-ever request, carrying the session id it was just given', function (): void {
    $response = $this->get('/');

    $sessionId = $response->getCookie(NameRequestVisitor::SESSION_COOKIE)?->getValue();

    expect(analyticsVisits()->sole()->session_id)->toBe($sessionId);
});

it('writes the visit through the analytics connection, never the default one', function (): void {
    /** @var list<QueryExecuted> $queries */
    $queries = [];

    DB::connection()->listen(function (QueryExecuted $query) use (&$queries): void {
        $queries[] = $query;
    });

    $this->get('/');

    $mentioning = fn (string $connection): array => array_values(array_filter(
        $queries,
        fn (QueryExecuted $query): bool => $query->connectionName === $connection && str_contains($query->sql, 'analytics_visits'),
    ));

    expect($mentioning('sqlite'))->toBe([])
        ->and($mentioning('analytics'))->not->toBe([]);
});

it('still answers and logs a warning when the analytics store cannot be flushed to', function (): void {
    $log = CapturedStory::capture();

    AnalyticsStoreFixtures::withUnwritableStore(function () use ($log): void {
        $this->get('/admin/login')->assertOk();

        expect($log->line('app.log', 'doing')['level'])->toBe('warn');
    });
});
