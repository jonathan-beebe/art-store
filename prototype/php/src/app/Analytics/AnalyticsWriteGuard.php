<?php

declare(strict_types=1);

namespace App\Analytics;

use Closure;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs one write against the analytics store (config/database.php) and
 * turns a failure into a log line instead of a thrown exception. The store
 * sits beside the commerce connection precisely so that its own
 * trouble — a missing directory, a write that outlasts its busy timeout —
 * never reaches the request a shopper or seller is waiting on.
 */
final class AnalyticsWriteGuard
{
    /**
     * Runs $write and returns what it returns. A $write that throws never
     * propagates: the guard logs one warning naming the analytics database
     * file and returns null instead.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $write
     * @return TReturn|null
     */
    public static function attempt(Closure $write): mixed
    {
        try {
            return $write();
        } catch (Throwable $e) {
            Log::warning("analytics write failed: {$e->getMessage()}", [
                'data' => ['analytics_database_file' => config('database.connections.analytics.database')],
            ]);

            return null;
        }
    }
}
