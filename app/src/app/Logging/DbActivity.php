<?php

declare(strict_types=1);

namespace App\Logging;

use Illuminate\Database\Events\QueryExecuted;

/**
 * The current request's database work, tallied as queries land: how many ran
 * and how long they took together. `LogRequestStory` resets the tally when a
 * request opens and reads it when the request's `did` line is written, so
 * the line itself answers "was it the database" (docs/spec.md §2.2).
 *
 * A static tally: `LoggingServiceProvider` registers the `DB::listen`
 * callback once per application boot, and PHP's one-request-per-process
 * model (see that provider) keeps one tally per request outside of tests.
 * A test process reuses one application across many simulated requests,
 * carrying the tally forward between them unless `reset()` runs on every
 * request.
 */
final class DbActivity
{
    private function __construct() {} // @codeCoverageIgnore

    private static int $queries = 0;

    private static float $totalMs = 0.0;

    public static function listen(QueryExecuted $query): void
    {
        self::$queries++;
        self::$totalMs += $query->time;
    }

    public static function reset(): void
    {
        self::$queries = 0;
        self::$totalMs = 0.0;
    }

    /**
     * @return array{queries: int, total_ms: float}
     */
    public static function snapshot(): array
    {
        return [
            'queries' => self::$queries,
            'total_ms' => round(self::$totalMs, 2),
        ];
    }
}
