<?php

declare(strict_types=1);

namespace Tests;

use App\Logging\LogLine;
use App\Logging\LogStore;
use App\Logging\LogStoreHandler;
use App\Logging\StoryFormatter;
use Illuminate\Log\Logger as ApplicationLogger;
use Illuminate\Support\Facades\Log;
use Monolog\Logger as Monolog;
use PDO;
use RuntimeException;

/**
 * Shared fixtures for the log store's own tests (`App\Logging\LogStoreTest`,
 * `LogStoreHandlerTest`, `LogStoreTapTest`), which build `App\Logging\LogStore`
 * directly against a temp file rather than through the container. A real,
 * Composer-autoloaded class rather than functions duplicated per file, or
 * declared in one sidecar and called from another — which would tie their
 * availability to whatever order Pest happens to require the test files in.
 */
final class LogStoreFixtures
{
    public const string LOG_LINES_TABLE_SQL = <<<'SQL'
        CREATE TABLE log_lines (
          id INTEGER PRIMARY KEY, ts TEXT NOT NULL, level TEXT, event TEXT, phase TEXT,
          msg TEXT, request_id TEXT, session_id TEXT, actor_type TEXT, actor_id TEXT,
          txn_id TEXT, duration_ms INTEGER, data TEXT, error TEXT, raw TEXT NOT NULL
        )
        SQL;

    public static function tempFile(): string
    {
        $file = tempnam(sys_get_temp_dir(), 'log_store_test_');
        unlink($file);

        return $file.'.sqlite3';
    }

    /**
     * Puts `$store` behind the `Log` facade for the rest of the test, through
     * the same {@see LogStoreHandler} `App\Logging\LogStoreTap` splices onto
     * the `stdout` channel — every `Story::` line reaches `$store`, not only
     * the lines a command appends to it directly. The test environment's
     * default channel is `null` (config/logging.php), so nothing written
     * through the `Log` facade reaches `$store` on its own.
     */
    public static function captureInto(LogStore $store): void
    {
        Log::swap(new ApplicationLogger(new Monolog('testing', [new LogStoreHandler($store, new StoryFormatter)])));
    }

    public static function line(string $event = 'order.place', string $ts = '2026-08-23T18:00:00.001Z'): LogLine
    {
        return LogLine::parse(json_encode(['ts' => $ts, 'event' => $event], JSON_THROW_ON_ERROR));
    }

    public static function connectionOrFail(LogStore $store): PDO
    {
        return $store->connection ?? throw new RuntimeException('expected the store to be enabled');
    }

    public static function rowCount(PDO $connection): int
    {
        return (int) self::scalar($connection, 'SELECT COUNT(*) FROM log_lines');
    }

    public static function scalar(PDO $connection, string $sql): int|string|null
    {
        $statement = $connection->query($sql);
        $value = $statement === false ? null : $statement->fetchColumn();

        return is_int($value) || is_string($value) ? $value : null;
    }

    /**
     * @return list<string>
     */
    public static function column(PDO $connection, string $sql): array
    {
        $statement = $connection->query($sql);
        $values = $statement === false ? [] : $statement->fetchAll(PDO::FETCH_COLUMN);

        return array_values(array_filter($values, 'is_string'));
    }
}
