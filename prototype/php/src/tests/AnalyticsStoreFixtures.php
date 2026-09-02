<?php

declare(strict_types=1);

namespace Tests;

use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Shared fixture for tests that point the analytics connection at an
 * unwritable path (`MergeAnonymousCustomerTest`, `RecordListingEventTest`,
 * `Shop\ListingControllerTest`, `RollUpPageViewsTest`). A real,
 * Composer-autoloaded class rather than functions duplicated per file, or
 * declared in one sidecar and called from another — which would tie their
 * availability to whatever order Pest happens to require the test files in.
 */
final class AnalyticsStoreFixtures
{
    /**
     * Points the analytics connection at a path that cannot be written to,
     * runs $body, and restores the connection to its prior database
     * afterward, whether or not $body throws.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $body
     * @return TReturn
     */
    public static function withUnwritableStore(Closure $body): mixed
    {
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
            return $body();
        } finally {
            if ($originalPdo->inTransaction()) {
                $originalPdo->rollBack();
            }

            config()->set('database.connections.analytics.database', $originalDatabase);
            DB::purge('analytics');
        }
    }
}
