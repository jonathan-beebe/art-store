<?php

declare(strict_types=1);

namespace App\Analytics;

use App\Domain\Analytics\PageViewDay;
use App\Domain\Analytics\PageViewSite;
use App\Models\PageViewCount;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The one writer to the analytics store (config/database.php). Recording
 * does no I/O: {@see recordEvent()}, {@see recordPageView()},
 * {@see recordVisit()}, and {@see claimVisit()} only append to an
 * in-memory buffer, so nothing a shopper or seller is waiting on ever waits
 * on the analytics connection.
 * {@see flush()} is where the buffer
 * becomes rows — {@see \App\Providers\AnalyticsServiceProvider} is what
 * decides when that happens. {@see prune()} is
 * {@see \App\Console\Commands\Sweep\SweepAnalytics}'s own entry point,
 * outside the buffer-and-flush path. {@see asRequest()} stands
 * in for a request outside one, for a console command driving real actions.
 */
final class Analytics
{
    /** Buffered rows that trigger an immediate flush, and the row count one
     * chunked `INSERT OR IGNORE` carries at most — see
     * {@see \App\Logging\LogStore}, the same precedent. */
    private const int FLUSH_AT = 256;

    /** Rows one retention DELETE takes at most, so the write lock a batch
     * holds stays brief — {@see \App\Logging\LogStore::prune()}, the same
     * shape. */
    private const int PRUNE_BATCH = 5000;

    /**
     * The facts {@see asRequest()} is standing in for the current request
     * with, or null outside any such scope. {@see recordEvent()} reads this
     * ahead of {@see RequestFacts::current()}, so an action called from
     * inside `asRequest()`'s closure carries the scope's facts even though
     * it never receives them as an argument.
     */
    private ?RequestFacts $requestOverride = null;

    /** @var list<AnalyticsEvent> */
    private array $events = [];

    /**
     * One entry per (site, path pattern, day), so two views of the same
     * pattern in one buffer flush as a single upsert carrying `count + 2`.
     *
     * @var array<string, array{site: PageViewSite, pathPattern: string, day: string, hits: int}>
     */
    private array $pageViews = [];

    /**
     * Keyed by session id, so two visits recorded for the same session
     * before one flush keep only the first — the same first-touch rule
     * `INSERT OR IGNORE` on `session_id` enforces once the row reaches the
     * table.
     *
     * @var array<string, AnalyticsVisit>
     */
    private array $visits = [];

    /**
     * Session id => the actor to claim the visit for, keyed the same way
     * as {@see $visits} so two claims for one session before a flush keep
     * only the last — the visitor a session ends a request as is the one
     * worth claiming it for. {@see claimVisit()}.
     *
     * @var array<string, string>
     */
    private array $claims = [];

    /**
     * Registers the process-exit fallback flush. `$registerShutdown`
     * defaults to `register_shutdown_function`; a test passes its own
     * closure to trigger the exit flush directly, without ending the
     * process.
     */
    public function __construct(?Closure $registerShutdown = null)
    {
        $registerShutdown ??= register_shutdown_function(...);
        $registerShutdown($this->flush(...));
    }

    /**
     * Buffers one event. An event missing its own ip or session takes on
     * the request behind it — {@see asRequest()}'s facts, inside
     * that scope, or {@see RequestFacts::current()} otherwise — so every
     * caller can hand over what happened and let this fill in where it came
     * from; an event a caller already gave explicit facts to is buffered
     * unchanged. Flushes immediately once the buffer reaches `FLUSH_AT`.
     */
    public function recordEvent(AnalyticsEvent $event): void
    {
        $this->events[] = $event->ip === null && $event->sessionId === null
            ? $event->withRequestFacts($this->requestOverride ?? RequestFacts::current())
            : $event;

        $this->flushIfAtCap();
    }

    /**
     * Runs `$body` with `recordEvent()` treating `$facts` as the current
     * request throughout — including calls an action injected into `$body`
     * makes on this same instance, since the override lives here rather
     * than on the call stack. Built for a console command driving real
     * actions with a backdated `$now` and no HTTP request behind them: it
     * stands in the ip, session, and request id a browser would have
     * carried. Restores the enclosing scope's facts (or none) once `$body`
     * returns or throws, so a nested call scopes only its own closure.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $body
     * @return TReturn
     */
    public function asRequest(RequestFacts $facts, Closure $body): mixed
    {
        $previous = $this->requestOverride;
        $this->requestOverride = $facts;

        try {
            return $body();
        } finally {
            $this->requestOverride = $previous;
        }
    }

    /**
     * Adds one hit to the in-memory count for a site, a route pattern, and
     * a day, flushing immediately once the buffer reaches `FLUSH_AT`.
     */
    public function recordPageView(PageViewSite $site, string $pathPattern, DateTimeImmutable $at): void
    {
        $day = PageViewDay::of($at);
        $key = implode('|', [$site->value, $pathPattern, $day]);

        if (isset($this->pageViews[$key])) {
            $this->pageViews[$key]['hits']++;
        } else {
            $this->pageViews[$key] = ['site' => $site, 'pathPattern' => $pathPattern, 'day' => $day, 'hits' => 1];
        }

        $this->flushIfAtCap();
    }

    /**
     * Buffers one visit, keyed by its own session id so a later request
     * for the same session recorded before this flush never overwrites
     * the first. Flushes immediately once the buffer reaches `FLUSH_AT`.
     */
    public function recordVisit(AnalyticsVisit $visit): void
    {
        $this->visits[$visit->sessionId] ??= $visit;

        $this->flushIfAtCap();
    }

    /**
     * Buffers a claim on the visit a session's first-touch row belongs to:
     * once the visitor behind a session becomes a customer,
     * `App\Shop\CustomerIdentity::commit()` calls this so the row's
     * `actor_id` — null until then, since the request that opened the
     * session may not be the one that tracks its first event — is filled
     * with the id the visit belongs to all along. Applied inside
     * {@see flush()}'s own transaction, after the visit inserts: a session
     * whose first touch is this very request finds its row already
     * carrying the actor, so the update is a no-op, and a session whose
     * visit row was written by an earlier request finds its null filled.
     * Flushes immediately once the buffer reaches `FLUSH_AT`.
     */
    public function claimVisit(string $sessionId, string $actorId): void
    {
        $this->claims[$sessionId] = $actorId;

        $this->flushIfAtCap();
    }

    /**
     * Writes the buffer in one transaction on the analytics connection and
     * clears it before the write runs — a batch that fails to write is
     * dropped, so a second `flush()` call (the process-exit fallback, after
     * `App\Providers\AnalyticsServiceProvider` already flushed once) finds
     * an empty buffer and does nothing.
     */
    public function flush(): void
    {
        if ($this->events === [] && $this->pageViews === [] && $this->visits === [] && $this->claims === []) {
            return;
        }

        $events = $this->events;
        $pageViews = $this->pageViews;
        $visits = $this->visits;
        $claims = $this->claims;
        $this->events = [];
        $this->pageViews = [];
        $this->visits = [];
        $this->claims = [];

        try {
            $this->writeBatch($events, $pageViews, $visits, $claims);
        } catch (Throwable $e) {
            $this->reportFailure('flush', $e, count($events) + count($pageViews) + count($visits) + count($claims));
        }
    }

    /**
     * Re-points every buffered-and-flushed row an anonymous customer owns
     * — both `analytics_events` rows and `analytics_visits` rows — to the
     * verified identity they merged into — one immediate write to each
     * table, outside the buffer, for
     * {@see \App\Actions\Customers\MergeAnonymousCustomer}. Never throws: a
     * failure logs the same one warning `flush()` does and leaves the rows
     * pointing at the anonymous id.
     */
    public function reassignActor(string $fromActorId, string $toActorId): void
    {
        try {
            DB::connection('analytics')->table('analytics_events')
                ->where('actor_id', $fromActorId)
                ->update(['actor_id' => $toActorId]);

            DB::connection('analytics')->table('analytics_visits')
                ->where('actor_id', $fromActorId)
                ->update(['actor_id' => $toActorId]);
        } catch (Throwable $e) {
            $this->reportFailure('reassignActor', $e);
        }
    }

    /**
     * Deletes every `analytics_events` row whose `occurred_at`, and every
     * `analytics_visits` row whose `first_seen_at`, is strictly before
     * `$cutoff`, each in `$batchSize`-row batches looped until none change,
     * through the analytics connection — {@see \App\Logging\LogStore::prune()}'s
     * shape. `page_view_counts` carries no personal data and is never
     * touched here. A failure here reaches the caller —
     * {@see \App\Console\Commands\Sweep\SweepAnalytics} decides what it means for
     * its exit code.
     */
    public function prune(DateTimeImmutable $cutoff, int $batchSize = self::PRUNE_BATCH): int
    {
        $cutoffTs = $cutoff->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        return self::pruneTable('analytics_events', 'occurred_at', $cutoffTs, $batchSize)
            + self::pruneTable('analytics_visits', 'first_seen_at', $cutoffTs, $batchSize);
    }

    /**
     * One table's own batched delete loop, shared by {@see prune()}'s two
     * calls: `analytics_events` against `occurred_at`, `analytics_visits`
     * against `first_seen_at`.
     */
    private static function pruneTable(string $table, string $column, string $cutoffTs, int $batchSize): int
    {
        $deleted = 0;

        do {
            $chunkDeleted = DB::connection('analytics')->table($table)
                ->where($column, '<', $cutoffTs)
                ->limit($batchSize)
                ->delete();
            $deleted += $chunkDeleted;
        } while ($chunkDeleted > 0);

        return $deleted;
    }

    /** Buffered rows not yet written — every distinct page-view key counts once, however many hits it carries. */
    public function pending(): int
    {
        return count($this->events) + count($this->pageViews) + count($this->visits) + count($this->claims);
    }

    private function flushIfAtCap(): void
    {
        if ($this->pending() >= self::FLUSH_AT) {
            $this->flush();
        }
    }

    /**
     * One `BEGIN IMMEDIATE` transaction around every write in the batch —
     * `IMMEDIATE` acquires the write lock at `BEGIN`, so a concurrent flush
     * fails fast against `busy_timeout`; a `DEFERRED` transaction risks
     * blocking on a lock upgrade at its first write. Skipped when the
     * connection is already inside a transaction (`Tests\TestCase`'s
     * `RefreshDatabase` wrapper): the writes join that already-open
     * transaction, and its own commit or rollback decides their fate.
     *
     * @param  list<AnalyticsEvent>  $events
     * @param  array<string, array{site: PageViewSite, pathPattern: string, day: string, hits: int}>  $pageViews
     * @param  array<string, AnalyticsVisit>  $visits
     * @param  array<string, string>  $claims
     */
    private function writeBatch(array $events, array $pageViews, array $visits, array $claims): void
    {
        $pdo = DB::connection('analytics')->getPdo();
        $ownsTransaction = ! $pdo->inTransaction();

        if ($ownsTransaction) {
            $pdo->exec('BEGIN IMMEDIATE');
        }

        try {
            $this->insertEvents($events);
            $this->upsertPageViews($pageViews);
            $this->insertVisits($visits);
            $this->applyClaims($claims);
        } catch (Throwable $e) {
            if ($ownsTransaction) {
                $pdo->exec('ROLLBACK');
            }

            throw $e;
        }

        if ($ownsTransaction) {
            $pdo->exec('COMMIT');
        }
    }

    /**
     * `OR IGNORE` ignores any constraint violation on a row — a NOT NULL
     * violation on a malformed event is dropped the same silent way as a
     * `dedupe_key` collision.
     *
     * @param  list<AnalyticsEvent>  $events
     */
    private function insertEvents(array $events): void
    {
        foreach (array_chunk($events, self::FLUSH_AT) as $chunk) {
            DB::connection('analytics')->table('analytics_events')->insertOrIgnore(
                array_map(fn (AnalyticsEvent $event): array => $event->columns(), $chunk),
            );
        }
    }

    /**
     * @param  array<string, array{site: PageViewSite, pathPattern: string, day: string, hits: int}>  $pageViews
     */
    private function upsertPageViews(array $pageViews): void
    {
        foreach ($pageViews as $pageView) {
            PageViewCount::query()->upsert(
                [
                    'site' => $pageView['site']->value,
                    'path_pattern' => $pageView['pathPattern'],
                    'day' => $pageView['day'],
                    'count' => $pageView['hits'],
                ],
                ['site', 'path_pattern', 'day'],
                // `excluded.count` is this row's own hit count — the value
                // the insert half of the statement carries — so the sum
                // never spells the flush's hit count into the SQL text.
                ['count' => DB::raw('page_view_counts.count + excluded.count')],
            );
        }
    }

    /**
     * `OR IGNORE` on the `session_id` primary key is what makes a visit
     * first-touch: the first request that carries a session id wins the
     * row, and every later request for the same session collides on the
     * key and is dropped.
     *
     * @param  array<string, AnalyticsVisit>  $visits
     */
    private function insertVisits(array $visits): void
    {
        if ($visits === []) {
            return;
        }

        DB::connection('analytics')->table('analytics_visits')->insertOrIgnore(
            array_map(fn (AnalyticsVisit $visit): array => $visit->columns(), $visits),
        );
    }

    /**
     * Runs after {@see insertVisits()} in the same transaction, so both
     * cases a claim exists for are settled in one flush: a session whose
     * first touch is this very request already inserted its row with the
     * actor set, so `actor_id IS NULL` no longer matches and the update
     * touches nothing; a session whose visit row an earlier request
     * already wrote finds its null filled. `actor_id IS NULL` also means a
     * claim never overwrites one a merge or an earlier claim already set.
     *
     * @param  array<string, string>  $claims
     */
    private function applyClaims(array $claims): void
    {
        foreach ($claims as $sessionId => $actorId) {
            DB::connection('analytics')->table('analytics_visits')
                ->where('session_id', $sessionId)
                ->whereNull('actor_id')
                ->update(['actor_id' => $actorId]);
        }
    }

    /**
     * The one shape every analytics write failure logs its warning in —
     * {@see flush()} and {@see reassignActor()} both call this, so an
     * operator greps `analytics_database_file` to find every line either
     * can write.
     */
    private function reportFailure(string $verb, Throwable $e, ?int $rows = null): void
    {
        try {
            $data = ['analytics_database_file' => config('database.connections.analytics.database')];

            if ($rows !== null) {
                $data['events'] = $rows;
            }

            Log::warning("analytics {$verb} failed: {$e->getMessage()}", ['data' => $data]);
        } catch (Throwable) {
            // The process-exit fallback can run this after the Laravel
            // container is gone, so config() and Log can themselves throw.
            // A report that cannot be written is dropped.
        }
    }
}
