<?php

declare(strict_types=1);

namespace App\Analytics;

use App\Domain\Analytics\AnalyticsEventName;
use App\Domain\Analytics\PageViewSite;
use App\Http\Middleware\LogRequestStory;
use App\Http\Middleware\NameRequestVisitor;
use App\Models\PageViewCount;
use Closure;
use DateTimeImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PDO;
use RuntimeException;
use stdClass;
use Tests\AnalyticsStoreFixtures;
use Tests\CapturedStory;

function listingViewedAt(DateTimeImmutable $at, ?string $dedupeKey = null): AnalyticsEvent
{
    return AnalyticsEvent::forListing(AnalyticsEventName::ListingView, 'lst_ABC', 'cus_XYZ', $at, $dedupeKey);
}

function visitFor(string $sessionId, DateTimeImmutable $at, string $landingPath = '/art/starry-night'): AnalyticsVisit
{
    return new AnalyticsVisit($sessionId, $at, $landingPath, null, null, null, null, null, null, null);
}

/**
 * @return array<string, mixed>
 */
function decodedData(stdClass $row): array
{
    /** @var string $data */
    $data = $row->data;

    /** @var array<string, mixed> $decoded */
    $decoded = json_decode($data, true);

    return $decoded;
}

it('buffers a recorded event without writing it', function (): void {
    $analytics = new Analytics;

    $analytics->recordEvent(listingViewedAt(new DateTimeImmutable));

    expect($analytics->pending())->toBe(1)
        ->and(DB::connection('analytics')->table('analytics_events')->count())->toBe(0);
});

it('writes the buffered event on flush, carrying the moment it was recorded rather than the moment it was flushed', function (): void {
    $analytics = new Analytics;
    $at = new DateTimeImmutable('2026-08-22T14:32:07+00:00');

    $this->travelTo($at);
    $analytics->recordEvent(listingViewedAt($at));

    $this->travelTo($at->modify('+3 days'));
    $analytics->flush();

    $row = DB::connection('analytics')->table('analytics_events')->sole();

    expect($analytics->pending())->toBe(0)
        ->and($row->id)->toStartWith('aev_')
        ->and($row->name)->toBe(AnalyticsEventName::ListingView->value)
        ->and($row->occurred_at)->toBe('2026-08-22 14:32:07')
        ->and($row->subject_type)->toBe('listing')
        ->and($row->subject_id)->toBe('lst_ABC')
        ->and($row->actor_id)->toBe('cus_XYZ');
});

it('records null ip, session, and request id for an event with no request behind it', function (): void {
    $analytics = new Analytics;

    $analytics->recordEvent(listingViewedAt(new DateTimeImmutable));
    $analytics->flush();

    $row = DB::connection('analytics')->table('analytics_events')->sole();

    expect($row->ip)->toBeNull()
        ->and($row->session_id)->toBeNull()
        ->and(decodedData($row))->toBe([]);
});

it('records the ip, session, and request id of the request current when the event was recorded', function (): void {
    $request = Request::create('/', server: ['REMOTE_ADDR' => '203.0.113.9']);
    $request->attributes->set(LogRequestStory::REQUEST_ID_ATTRIBUTE, 'req_01J00000000000000000000ABC');
    $request->cookies->set(NameRequestVisitor::SESSION_COOKIE, 'ses_01J00000000000000000000ABC');
    $this->app->instance('request', $request);

    $analytics = new Analytics;
    $analytics->recordEvent(listingViewedAt(new DateTimeImmutable));
    $analytics->flush();

    $row = DB::connection('analytics')->table('analytics_events')->sole();

    expect($row->ip)->toBe('203.0.113.9')
        ->and($row->session_id)->toBe('ses_01J00000000000000000000ABC')
        ->and(decodedData($row))->toBe(['request_id' => 'req_01J00000000000000000000ABC']);
});

it('leaves an event\'s own ip and session alone rather than filling them from the current request', function (): void {
    $request = Request::create('/', server: ['REMOTE_ADDR' => '203.0.113.9']);
    $request->attributes->set(LogRequestStory::REQUEST_ID_ATTRIBUTE, 'req_01J00000000000000000000ABC');
    $this->app->instance('request', $request);

    $event = new AnalyticsEvent(
        name: AnalyticsEventName::ListingView,
        occurredAt: new DateTimeImmutable,
        subjectType: 'listing',
        subjectId: 'lst_ABC',
        actorId: 'cus_XYZ',
        dedupeKey: null,
        ip: '198.51.100.7',
        sessionId: 'ses_EXPLICIT',
    );

    $analytics = new Analytics;
    $analytics->recordEvent($event);
    $analytics->flush();

    $row = DB::connection('analytics')->table('analytics_events')->sole();

    expect($row->ip)->toBe('198.51.100.7')
        ->and($row->session_id)->toBe('ses_EXPLICIT');
});

it('carries a scoped request\'s facts onto every event recorded inside it', function (): void {
    $facts = RequestFacts::of('203.0.113.55', 'ses_SCOPED', 'req_SCOPED');
    $analytics = new Analytics;

    $analytics->asRequest($facts, function () use ($analytics): void {
        $analytics->recordEvent(listingViewedAt(new DateTimeImmutable));
    });
    $analytics->flush();

    $row = DB::connection('analytics')->table('analytics_events')->sole();

    expect($row->ip)->toBe('203.0.113.55')
        ->and($row->session_id)->toBe('ses_SCOPED')
        ->and(decodedData($row))->toBe(['request_id' => 'req_SCOPED']);
});

it('lets a scoped request\'s facts win over a request bound in the container', function (): void {
    $request = Request::create('/', server: ['REMOTE_ADDR' => '203.0.113.9']);
    $request->attributes->set(LogRequestStory::REQUEST_ID_ATTRIBUTE, 'req_CONTAINER');
    $this->app->instance('request', $request);

    $facts = RequestFacts::of('203.0.113.55', 'ses_SCOPED', 'req_SCOPED');
    $analytics = new Analytics;

    $analytics->asRequest($facts, function () use ($analytics): void {
        $analytics->recordEvent(listingViewedAt(new DateTimeImmutable));
    });
    $analytics->flush();

    expect(DB::connection('analytics')->table('analytics_events')->sole()->ip)->toBe('203.0.113.55');
});

it('returns the scoped closure\'s own return value', function (): void {
    $analytics = new Analytics;

    $result = $analytics->asRequest(RequestFacts::of(null, null, null), fn (): string => 'the closure ran');

    expect($result)->toBe('the closure ran');
});

it('restores the enclosing scope once a nested asRequest call returns', function (): void {
    $outer = RequestFacts::of('203.0.113.1', 'ses_OUTER', null);
    $inner = RequestFacts::of('203.0.113.2', 'ses_INNER', null);
    $analytics = new Analytics;

    $analytics->asRequest($outer, function () use ($analytics, $inner): void {
        $analytics->asRequest($inner, function () use ($analytics): void {
            $analytics->recordEvent(listingViewedAt(new DateTimeImmutable));
        });

        $analytics->recordEvent(listingViewedAt(new DateTimeImmutable));
    });
    $analytics->flush();

    $sessions = DB::connection('analytics')->table('analytics_events')->orderBy('id')->pluck('session_id')->all();

    expect($sessions)->toBe(['ses_INNER', 'ses_OUTER']);
});

it('restores the enclosing scope even when the scoped closure throws', function (): void {
    $analytics = new Analytics;

    try {
        $analytics->asRequest(RequestFacts::of(null, 'ses_SCOPED', null), function (): void {
            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
        // The scope must unwind before this catch runs.
    }

    $analytics->recordEvent(listingViewedAt(new DateTimeImmutable));
    $analytics->flush();

    expect(DB::connection('analytics')->table('analytics_events')->sole()->session_id)->toBeNull();
});

it('does nothing when flushed with an empty buffer', function (): void {
    (new Analytics)->flush();

    expect(DB::connection('analytics')->table('analytics_events')->count())->toBe(0);
});

it('ignores a second event carrying a dedupe key already written', function (): void {
    $analytics = new Analytics;
    $dedupeKey = 'listing:lst_ABC:customer:cus_XYZ:hour:2026-08-22T14';

    $analytics->recordEvent(listingViewedAt(new DateTimeImmutable('2026-08-22T14:05:00+00:00'), $dedupeKey));
    $analytics->flush();
    $analytics->recordEvent(listingViewedAt(new DateTimeImmutable('2026-08-22T14:40:00+00:00'), $dedupeKey));
    $analytics->flush();

    expect(DB::connection('analytics')->table('analytics_events')->count())->toBe(1);
});

it('rolls two page views of the same pattern up into one upsert', function (): void {
    $analytics = new Analytics;
    $at = new DateTimeImmutable('2026-08-22T14:00:00+00:00');

    $analytics->recordPageView(PageViewSite::Shop, '/art/{listing}', $at);
    $analytics->recordPageView(PageViewSite::Shop, '/art/{listing}', $at);
    $analytics->flush();

    $row = PageViewCount::query()->sole();

    expect($row->site)->toBe(PageViewSite::Shop->value)
        ->and($row->path_pattern)->toBe('/art/{listing}')
        ->and($row->day)->toBe('2026-08-22')
        ->and($row->count)->toBe(2);
});

it('increments an existing page-view row rather than inserting a new one', function (): void {
    PageViewCount::factory()->create([
        'site' => PageViewSite::Shop->value,
        'path_pattern' => '/art/{listing}',
        'day' => '2026-08-22',
        'count' => 5,
    ]);

    $analytics = new Analytics;
    $analytics->recordPageView(PageViewSite::Shop, '/art/{listing}', new DateTimeImmutable('2026-08-22T14:00:00+00:00'));
    $analytics->flush();

    expect(PageViewCount::query()->sole()->count)->toBe(6);
});

it('counts a page view toward pending() once per distinct key, not once per hit', function (): void {
    $analytics = new Analytics;
    $at = new DateTimeImmutable('2026-08-22T14:00:00+00:00');

    $analytics->recordPageView(PageViewSite::Shop, '/art/{listing}', $at);
    $analytics->recordPageView(PageViewSite::Shop, '/art/{listing}', $at);
    $analytics->recordPageView(PageViewSite::Seller, '/seller', $at);

    expect($analytics->pending())->toBe(2);
});

it('buffers a recorded visit without writing it', function (): void {
    $analytics = new Analytics;

    $analytics->recordVisit(visitFor('ses_01J00000000000000000000ABC', new DateTimeImmutable));

    expect($analytics->pending())->toBe(1)
        ->and(DB::connection('analytics')->table('analytics_visits')->count())->toBe(0);
});

it('writes the buffered visit on flush', function (): void {
    $analytics = new Analytics;
    $at = new DateTimeImmutable('2026-08-22T14:32:07+00:00');

    $analytics->recordVisit(visitFor('ses_01J00000000000000000000ABC', $at, '/art/starry-night'));
    $analytics->flush();

    $row = DB::connection('analytics')->table('analytics_visits')->sole();

    expect($row->session_id)->toBe('ses_01J00000000000000000000ABC')
        ->and($row->first_seen_at)->toBe('2026-08-22 14:32:07')
        ->and($row->landing_path)->toBe('/art/starry-night');
});

it('keeps the first visit recorded for a session within one buffer', function (): void {
    $analytics = new Analytics;
    $first = new DateTimeImmutable('2026-08-22T14:00:00+00:00');
    $second = new DateTimeImmutable('2026-08-22T15:00:00+00:00');

    $analytics->recordVisit(visitFor('ses_01J00000000000000000000ABC', $first, '/art/first'));
    $analytics->recordVisit(visitFor('ses_01J00000000000000000000ABC', $second, '/art/second'));
    $analytics->flush();

    expect(DB::connection('analytics')->table('analytics_visits')->sole()->landing_path)->toBe('/art/first');
});

it('keeps the first visit recorded for a session across two flushes', function (): void {
    $analytics = new Analytics;
    $first = new DateTimeImmutable('2026-08-22T14:00:00+00:00');
    $second = new DateTimeImmutable('2026-08-22T15:00:00+00:00');

    $analytics->recordVisit(visitFor('ses_01J00000000000000000000ABC', $first, '/art/first'));
    $analytics->flush();
    $analytics->recordVisit(visitFor('ses_01J00000000000000000000ABC', $second, '/art/second'));
    $analytics->flush();

    expect(DB::connection('analytics')->table('analytics_visits')->sole()->landing_path)->toBe('/art/first');
});

it('flushes automatically at the row cap', function (): void {
    $analytics = new Analytics;

    for ($i = 0; $i < 255; $i++) {
        $analytics->recordEvent(listingViewedAt(new DateTimeImmutable));
    }

    expect(DB::connection('analytics')->table('analytics_events')->count())->toBe(0);

    $analytics->recordEvent(listingViewedAt(new DateTimeImmutable));

    expect($analytics->pending())->toBe(0)
        ->and(DB::connection('analytics')->table('analytics_events')->count())->toBe(256);
});

it('flushes automatically at the row cap when only page views are buffered', function (): void {
    $analytics = new Analytics;
    $at = new DateTimeImmutable('2026-08-22T14:00:00+00:00');

    for ($i = 0; $i < 255; $i++) {
        $analytics->recordPageView(PageViewSite::Shop, "/art/{$i}", $at);
    }

    expect(PageViewCount::query()->count())->toBe(0);

    $analytics->recordPageView(PageViewSite::Shop, '/art/255', $at);

    expect($analytics->pending())->toBe(0)
        ->and(PageViewCount::query()->count())->toBe(256);
});

it('flushes automatically at the row cap when only visits are buffered', function (): void {
    $analytics = new Analytics;
    $at = new DateTimeImmutable('2026-08-22T14:00:00+00:00');

    for ($i = 0; $i < 255; $i++) {
        $analytics->recordVisit(visitFor("ses_{$i}", $at));
    }

    expect(DB::connection('analytics')->table('analytics_visits')->count())->toBe(0);

    $analytics->recordVisit(visitFor('ses_255', $at));

    expect($analytics->pending())->toBe(0)
        ->and(DB::connection('analytics')->table('analytics_visits')->count())->toBe(256);
});

it('commits through its own BEGIN IMMEDIATE transaction against a real file outside any outer transaction', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'analytics-write-batch-');
    $originalDatabase = config('database.connections.analytics.database');
    $originalPdo = DB::connection('analytics')->getPdo();

    config()->set('database.connections.analytics.database', $path);
    DB::purge('analytics');

    /** @var Migration $migration */
    $migration = require database_path('migrations/2026_09_02_000100_create_analytics_events_table.php');
    // Every migration is an anonymous class defining its own up(); the base
    // Migration class declares none for the analyser to see.
    // @phpstan-ignore-next-line
    $migration->up();

    try {
        expect(DB::connection('analytics')->getPdo()->inTransaction())->toBeFalse();

        $analytics = new Analytics;
        $analytics->recordEvent(listingViewedAt(new DateTimeImmutable('2026-08-22T14:32:07+00:00')));
        $analytics->flush();

        $pdo = new PDO('sqlite:'.$path);
        $statement = $pdo->query('SELECT name, occurred_at FROM analytics_events');
        assert($statement !== false);
        /** @var list<array{name: string, occurred_at: string}> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        expect($rows)->toHaveCount(1)
            ->and($rows[0]['name'])->toBe(AnalyticsEventName::ListingView->value)
            ->and($rows[0]['occurred_at'])->toBe('2026-08-22 14:32:07');
    } finally {
        if ($originalPdo->inTransaction()) {
            $originalPdo->rollBack();
        }

        config()->set('database.connections.analytics.database', $originalDatabase);
        DB::purge('analytics');

        foreach ([$path, $path.'-wal', $path.'-shm'] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
});

it('logs one warning and drops the batch when the store cannot be written to', function (): void {
    $log = CapturedStory::capture();

    AnalyticsStoreFixtures::withUnwritableStore(function () use ($log): void {
        $analytics = new Analytics;
        $analytics->recordEvent(listingViewedAt(new DateTimeImmutable));

        $analytics->flush();

        $line = $log->line('app.log', 'doing');

        expect($analytics->pending())->toBe(0)
            ->and($line['level'])->toBe('warn')
            ->and($line['data'])->toBe([
                'analytics_database_file' => config('database.connections.analytics.database'),
                'events' => 1,
            ]);
    });
});

it('moves every row an actor owns to another actor', function (): void {
    $analytics = new Analytics;
    $analytics->recordEvent(listingViewedAt(new DateTimeImmutable));
    $analytics->flush();

    $analytics->reassignActor('cus_XYZ', 'cus_MERGED');

    expect(DB::connection('analytics')->table('analytics_events')->sole()->actor_id)->toBe('cus_MERGED');
});

it('moves every visit an actor owns to another actor', function (): void {
    $analytics = new Analytics;
    $visit = new AnalyticsVisit('ses_01J00000000000000000000ABC', new DateTimeImmutable, '/', null, null, null, null, null, null, 'cus_XYZ');
    $analytics->recordVisit($visit);
    $analytics->flush();

    $analytics->reassignActor('cus_XYZ', 'cus_MERGED');

    expect(DB::connection('analytics')->table('analytics_visits')->sole()->actor_id)->toBe('cus_MERGED');
});

it('logs one warning and never throws when reassignActor cannot write', function (): void {
    $log = CapturedStory::capture();

    AnalyticsStoreFixtures::withUnwritableStore(function () use ($log): void {
        (new Analytics)->reassignActor('cus_XYZ', 'cus_MERGED');

        expect($log->line('app.log', 'doing')['level'])->toBe('warn');
    });
});

it('deletes events before the cutoff in batches, looping until none change', function (): void {
    $analytics = new Analytics;

    foreach (['2026-08-01', '2026-08-02', '2026-08-03', '2026-08-10', '2026-08-11'] as $day) {
        $analytics->recordEvent(listingViewedAt(new DateTimeImmutable("{$day}T00:00:00+00:00")));
    }
    $analytics->flush();

    $deleted = $analytics->prune(new DateTimeImmutable('2026-08-05T00:00:00+00:00'), batchSize: 2);

    expect($deleted)->toBe(3)
        ->and(DB::connection('analytics')->table('analytics_events')->count())->toBe(2);
});

it('prunes nothing when every event is at or after the cutoff', function (): void {
    $analytics = new Analytics;
    $analytics->recordEvent(listingViewedAt(new DateTimeImmutable('2026-08-10T00:00:00+00:00')));
    $analytics->flush();

    $deleted = $analytics->prune(new DateTimeImmutable('2026-08-05T00:00:00+00:00'));

    expect($deleted)->toBe(0)
        ->and(DB::connection('analytics')->table('analytics_events')->count())->toBe(1);
});

it('keeps an event whose occurred_at exactly equals the cutoff', function (): void {
    $analytics = new Analytics;
    $analytics->recordEvent(listingViewedAt(new DateTimeImmutable('2026-08-05T00:00:00+00:00')));
    $analytics->flush();

    $deleted = $analytics->prune(new DateTimeImmutable('2026-08-05T00:00:00+00:00'));

    expect($deleted)->toBe(0)
        ->and(DB::connection('analytics')->table('analytics_events')->count())->toBe(1);
});

it('deletes visits before the cutoff in batches, looping until none change', function (): void {
    $analytics = new Analytics;

    foreach (['2026-08-01', '2026-08-02', '2026-08-03', '2026-08-10', '2026-08-11'] as $day) {
        $analytics->recordVisit(new AnalyticsVisit("ses_{$day}", new DateTimeImmutable("{$day}T00:00:00+00:00"), '/', null, null, null, null, null, null, null));
    }
    $analytics->flush();

    $deleted = $analytics->prune(new DateTimeImmutable('2026-08-05T00:00:00+00:00'), batchSize: 2);

    expect($deleted)->toBe(3)
        ->and(DB::connection('analytics')->table('analytics_visits')->count())->toBe(2);
});

it('prunes nothing when every visit is at or after the cutoff', function (): void {
    $analytics = new Analytics;
    $analytics->recordVisit(new AnalyticsVisit('ses_new', new DateTimeImmutable('2026-08-10T00:00:00+00:00'), '/', null, null, null, null, null, null, null));
    $analytics->flush();

    $deleted = $analytics->prune(new DateTimeImmutable('2026-08-05T00:00:00+00:00'));

    expect($deleted)->toBe(0)
        ->and(DB::connection('analytics')->table('analytics_visits')->count())->toBe(1);
});

it('keeps a visit whose first_seen_at exactly equals the cutoff', function (): void {
    $analytics = new Analytics;
    $analytics->recordVisit(new AnalyticsVisit('ses_edge', new DateTimeImmutable('2026-08-05T00:00:00+00:00'), '/', null, null, null, null, null, null, null));
    $analytics->flush();

    $deleted = $analytics->prune(new DateTimeImmutable('2026-08-05T00:00:00+00:00'));

    expect($deleted)->toBe(0)
        ->and(DB::connection('analytics')->table('analytics_visits')->count())->toBe(1);
});

it('sums events and visits pruned into one count', function (): void {
    $analytics = new Analytics;
    $analytics->recordEvent(listingViewedAt(new DateTimeImmutable('2026-08-01T00:00:00+00:00')));
    $analytics->recordVisit(new AnalyticsVisit('ses_old', new DateTimeImmutable('2026-08-01T00:00:00+00:00'), '/', null, null, null, null, null, null, null));
    $analytics->flush();

    $deleted = $analytics->prune(new DateTimeImmutable('2026-08-05T00:00:00+00:00'));

    expect($deleted)->toBe(2)
        ->and(DB::connection('analytics')->table('analytics_events')->count())->toBe(0)
        ->and(DB::connection('analytics')->table('analytics_visits')->count())->toBe(0);
});

it('lets a visits prune failure propagate, the same as an events prune failure', function (): void {
    $analytics = new Analytics;
    $analytics->recordVisit(new AnalyticsVisit('ses_old', new DateTimeImmutable('2026-08-01T00:00:00+00:00'), '/', null, null, null, null, null, null, null));
    $analytics->flush();

    DB::connection('analytics')->getPdo()->exec('DROP TABLE analytics_visits');

    expect(fn () => $analytics->prune(new DateTimeImmutable('2026-08-05T00:00:00+00:00')))
        ->toThrow(QueryException::class);
});

it('leaves page_view_counts alone — it carries no personal data', function (): void {
    $analytics = new Analytics;
    $analytics->recordEvent(listingViewedAt(new DateTimeImmutable('2026-08-01T00:00:00+00:00')));
    $analytics->recordPageView(PageViewSite::Shop, '/art/{listing}', new DateTimeImmutable('2026-08-01T00:00:00+00:00'));
    $analytics->flush();

    $analytics->prune(new DateTimeImmutable('2026-08-05T00:00:00+00:00'));

    expect(DB::connection('analytics')->table('analytics_events')->count())->toBe(0)
        ->and(PageViewCount::query()->count())->toBe(1);
});

it('lets a prune failure propagate, rather than swallowing it like flush()/reassignActor() do', function (): void {
    $analytics = new Analytics;
    $analytics->recordEvent(listingViewedAt(new DateTimeImmutable('2026-08-01T00:00:00+00:00')));
    $analytics->flush();

    DB::connection('analytics')->getPdo()->exec('DROP TABLE analytics_events');

    expect(fn () => $analytics->prune(new DateTimeImmutable('2026-08-05T00:00:00+00:00')))
        ->toThrow(QueryException::class);
});

it('never throws from the shutdown fallback once the container is unusable', function (): void {
    $captured = null;

    $analytics = new Analytics(registerShutdown: function (Closure $flush) use (&$captured): void {
        $captured = $flush;
    });
    $analytics->recordEvent(listingViewedAt(new DateTimeImmutable));
    assert($captured instanceof Closure);

    $db = $this->app->make('db');
    $config = $this->app->make('config');

    try {
        // Mirrors what process exit leaves behind for the real shutdown
        // fallback: both the write and the failure report reach for a
        // container that can no longer resolve them.
        $this->app->offsetUnset('db');
        $this->app->offsetUnset('config');

        $captured();
    } finally {
        $this->app->instance('db', $db);
        $this->app->instance('config', $config);
    }

    expect($analytics->pending())->toBe(0);
});

it('flushes what a request recorded once the response has already gone back', function (): void {
    Route::get('/analytics-test-route', function (): string {
        app(Analytics::class)->recordEvent(listingViewedAt(new DateTimeImmutable));

        return 'ok';
    })->middleware('web');

    $this->get('/analytics-test-route')->assertOk();

    expect(DB::connection('analytics')->table('analytics_events')->count())->toBe(1);
});
