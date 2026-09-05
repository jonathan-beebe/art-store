<?php

declare(strict_types=1);

namespace App\Support;

use App\Logging\StoryEvent;
use Illuminate\Database\Events\QueryExecuted;

/**
 * The moment one query crosses `LOG_SLOW_QUERY_MS` (config/log_store.php,
 * docs/spec.md §2.3), announced on the spot with a `query.exceed`
 * line: where it was issued, how long it took, and the query text it ran.
 * `DbActivity`'s `data.db` total on the request's closing line says a
 * request was slow; this says which query did it.
 *
 * Bindings never reach the line — a bound value can carry an email address
 * (a magic-link lookup), where the parameterized SQL text cannot.
 *
 * Stateless between queries: every call reads the configured threshold
 * fresh and, past it, writes from what the event itself carries. There is
 * no tally here for a request boundary to reset.
 */
final class SlowQueryWatch
{
    private function __construct() {} // @codeCoverageIgnore

    /** Flags a frame as framework code, skipped when `sourceFrame()` looks
     * for the call site that triggered the query. */
    private const string VENDOR_MARKER = '/vendor/';

    private const string UNKNOWN_SOURCE = 'unknown';

    public static function listen(QueryExecuted $query): void
    {
        $thresholdMs = config('log_store.slow_query_ms');

        if (! is_int($thresholdMs) || $query->time <= $thresholdMs) {
            return;
        }

        $durationMs = round($query->time, 2);

        Story::for(StoryEvent::QueryExceed)->did("a query took {$durationMs}ms, past the {$thresholdMs}ms threshold", [
            'source' => self::sourceFrame(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), __FILE__, base_path()),
            'duration_ms' => $durationMs,
            'sql' => $query->sql,
            'threshold_ms' => $thresholdMs,
        ]);
    }

    /**
     * Where the application issued the query that produced $backtrace,
     * `relative/path.php:line`: the first frame outside $ownFile and
     * `vendor/`, since every frame closer than that is the listener itself
     * and the framework carrying the query down to it. `"unknown"` when no
     * such frame exists.
     *
     * @param  array<int, array<string, mixed>>  $backtrace  as returned by debug_backtrace()
     */
    public static function sourceFrame(array $backtrace, string $ownFile, string $basePath): string
    {
        foreach ($backtrace as $frame) {
            $file = $frame['file'] ?? null;
            $line = $frame['line'] ?? null;

            if (is_string($file) && is_int($line) && $file !== $ownFile && ! str_contains($file, self::VENDOR_MARKER)) {
                return ltrim(str_replace($basePath, '', $file), '/').":{$line}";
            }
        }

        return self::UNKNOWN_SOURCE;
    }
}
