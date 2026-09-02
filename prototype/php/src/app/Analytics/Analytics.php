<?php

declare(strict_types=1);

namespace App\Analytics;

use App\Domain\Analytics\PageViewDay;
use App\Domain\Analytics\PageViewSite;
use App\Models\PageViewCount;
use Closure;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The one writer to the analytics store (config/database.php). Recording
 * does no I/O: {@see recordEvent()} and {@see recordPageView()} only append
 * to an in-memory buffer, so nothing a shopper or seller is waiting on ever
 * waits on the analytics connection. {@see flush()} is where the buffer
 * becomes rows — {@see \App\Providers\AnalyticsServiceProvider} is what
 * decides when that happens.
 */
final class Analytics
{
    /** Buffered rows that trigger an immediate flush, and the row count one
     * chunked `INSERT OR IGNORE` carries at most — see
     * {@see \App\Logging\LogStore}, the same precedent. */
    private const int FLUSH_AT = 256;

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
     * Buffers one event, carrying the ip, session, and request id of
     * whatever request is current ({@see RequestFacts::current()}) — every
     * caller hands over what happened and lets this fill in where it came
     * from. Flushes immediately once the buffer reaches `FLUSH_AT`.
     */
    public function recordEvent(AnalyticsEvent $event): void
    {
        $this->events[] = $event->withRequestFacts(RequestFacts::current());

        $this->flushIfAtCap();
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
     * Writes the buffer in one transaction on the analytics connection and
     * clears it before the write runs — a batch that fails to write is
     * dropped, so a second `flush()` call (the process-exit fallback, after
     * `App\Providers\AnalyticsServiceProvider` already flushed once) finds
     * an empty buffer and does nothing.
     */
    public function flush(): void
    {
        if ($this->events === [] && $this->pageViews === []) {
            return;
        }

        $events = $this->events;
        $pageViews = $this->pageViews;
        $this->events = [];
        $this->pageViews = [];

        try {
            $this->writeBatch($events, $pageViews);
        } catch (Throwable $e) {
            $this->reportFailure('flush', $e, count($events) + count($pageViews));
        }
    }

    /**
     * Re-points every buffered-and-flushed row an anonymous customer owns
     * to the verified identity they merged into — one immediate write,
     * outside the buffer, for
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
        } catch (Throwable $e) {
            $this->reportFailure('reassignActor', $e);
        }
    }

    /** Buffered rows not yet written — every distinct page-view key counts once, however many hits it carries. */
    public function pending(): int
    {
        return count($this->events) + count($this->pageViews);
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
     */
    private function writeBatch(array $events, array $pageViews): void
    {
        $pdo = DB::connection('analytics')->getPdo();
        $ownsTransaction = ! $pdo->inTransaction();

        if ($ownsTransaction) {
            $pdo->exec('BEGIN IMMEDIATE');
        }

        try {
            $this->insertEvents($events);
            $this->upsertPageViews($pageViews);
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
