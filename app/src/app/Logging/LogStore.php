<?php

declare(strict_types=1);

namespace App\Logging;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOStatement;
use RuntimeException;
use Throwable;

/**
 * The queryable mirror docs/logging.md describes: every stdout line, kept in
 * a SQLite file of its own. Three invariants shape this class:
 *
 * 1. Stdout is canonical — this class never touches it; the tap that feeds
 *    `append()` runs after the stdout handler, per `App\Logging\LogStoreTap`.
 * 2. The store is a mirror; it validates nothing (`App\Logging\LogLine`
 *    already turned the line into a row, malformed lines included).
 * 3. The store's failure is never the app's failure. Every public method
 *    here swallows its own errors, except `prune()`, whose caller
 *    (`orders:sweep`) is expected to report it and carries on regardless.
 *
 * PHP serves one request per process, so there is no event loop to schedule
 * a flush on: rows buffer in memory and flush at the row cap or when the
 * process exits (`register_shutdown_function`, not `$app->terminating()` —
 * that fires the `app.shutdown` line, which must itself reach the buffer
 * before the final flush runs).
 */
final class LogStore
{
    private const string OFF = 'off';

    /** Bumped when the DDL changes. A file ahead of this build is left
     * untouched and the store is disabled for the process — deleting the
     * file is the sanctioned escape hatch for a mismatch. */
    private const int SCHEMA_VERSION = 1;

    /** A contended flush must fail fast and re-buffer; the commerce
     * connection's timeout would stall the request behind it. */
    private const int BUSY_TIMEOUT_MS = 250;

    /** Buffered rows that trigger an immediate flush — also the row count
     * one multi-row INSERT carries at most, keeping bound parameters well
     * under SQLite's variable limit. */
    private const int FLUSH_AT = 256;

    /** Past this many buffered rows the store drops new ones — stdout
     * already carried them — until a flush succeeds. */
    private const int BUFFER_CAP = 10_000;

    /** Rows one retention DELETE takes at most, so the write lock is held
     * for milliseconds per batch and a concurrently flushing process
     * re-buffers at most one flush. */
    private const int PRUNE_BATCH = 5000;

    /** Freed pages `incremental_vacuum` hands back per prune; the
     * bootstrap's `auto_vacuum = INCREMENTAL` is what makes it work. */
    private const int VACUUM_PAGES = 1000;

    /** ISO-8601 UTC with milliseconds — `App\Logging\StoryFormatter`'s `ts`
     * shape, so a lexical `ts < ?` comparison stays correct at the second a
     * cutoff falls on. */
    private const string TIMESTAMP = 'Y-m-d\TH:i:s.v\Z';

    private const string DDL = <<<'SQL'
        CREATE TABLE IF NOT EXISTS log_lines (
          id          INTEGER PRIMARY KEY,
          ts          TEXT NOT NULL,
          level       TEXT,
          event       TEXT,
          phase       TEXT,
          msg         TEXT,
          request_id  TEXT,
          session_id  TEXT,
          actor_type  TEXT,
          actor_id    TEXT,
          txn_id      TEXT,
          duration_ms INTEGER,
          data        TEXT,
          error       TEXT,
          raw         TEXT NOT NULL
        );

        CREATE INDEX IF NOT EXISTS log_lines_ts         ON log_lines (ts);
        CREATE INDEX IF NOT EXISTS log_lines_event_ts   ON log_lines (event, ts);
        CREATE INDEX IF NOT EXISTS log_lines_level_ts   ON log_lines (level, ts);
        CREATE INDEX IF NOT EXISTS log_lines_request_id ON log_lines (request_id) WHERE request_id IS NOT NULL;
        CREATE INDEX IF NOT EXISTS log_lines_txn_id     ON log_lines (txn_id)     WHERE txn_id IS NOT NULL;
        CREATE INDEX IF NOT EXISTS log_lines_actor_id   ON log_lines (actor_id)   WHERE actor_id IS NOT NULL;
        SQL;

    private const string INSERT_COLUMNS = 'ts, level, event, phase, msg, request_id, session_id, actor_type, actor_id, txn_id, duration_ms, data, error, raw';

    private const string ROW_PLACEHOLDERS = '(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

    /** @var list<LogLine> */
    private array $buffer = [];

    /** @var array<int, PDOStatement> keyed by row count, so a repeated batch
     * size reuses its prepared statement. */
    private array $insertStatements = [];

    private bool $dropAnnounced = false;

    private function __construct(
        public readonly ?PDO $connection,
        private readonly Closure $stderrWriter,
        private readonly int $bufferCap = self::BUFFER_CAP,
    ) {}

    /**
     * Opens (bootstrapping if needed) the store over its own PDO handle and
     * registers a final flush on process exit. The literal `"off"` disables
     * the store quietly — that is a deliberate configuration, not a
     * failure. Any other open or bootstrap failure (missing directory,
     * corrupt file, a schema version ahead of this build) disables the
     * store after one `app.log`-shaped warn line on stdout; nothing thrown
     * here reaches the caller. `stdoutWriter`/`stderrWriter` are injectable
     * so a test can watch what would otherwise go to the real streams;
     * `registerShutdown` so a test can trigger the exit flush itself,
     * without waiting for the process to end; `bufferCap` so a test can
     * reach the drop path at a row count far below the production
     * `BUFFER_CAP`.
     */
    public static function open(
        string $file,
        ?Closure $stdoutWriter = null,
        ?Closure $stderrWriter = null,
        ?Closure $registerShutdown = null,
        ?int $bufferCap = null,
    ): self {
        $stdoutWriter ??= self::defaultWriter('php://stdout');
        $stderrWriter ??= self::defaultWriter('php://stderr');
        $registerShutdown ??= register_shutdown_function(...);
        $bufferCap ??= self::BUFFER_CAP;

        if ($file === self::OFF) {
            return new self(null, $stderrWriter, $bufferCap);
        }

        try {
            $connection = new PDO('sqlite:'.$file);
            $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            // First, outside any transaction, and before every other
            // pragma: SQLite only takes an auto_vacuum change while the
            // database is still entirely virgin — switching journal_mode
            // first already writes the WAL header and disqualifies it. A
            // no-op on a file that already has a schema. It is what lets
            // the retention prune's incremental_vacuum return pages.
            $connection->exec('PRAGMA auto_vacuum = INCREMENTAL');
            $connection->exec('PRAGMA journal_mode = WAL');
            $connection->exec('PRAGMA synchronous = NORMAL');
            $connection->exec('PRAGMA busy_timeout = '.self::BUSY_TIMEOUT_MS);
            self::ensureSchema($connection);
        } catch (Throwable $e) {
            self::warnDisabled($stdoutWriter, $file, $e);

            return new self(null, $stderrWriter, $bufferCap);
        }

        $store = new self($connection, $stderrWriter, $bufferCap);
        $registerShutdown($store->flush(...));

        return $store;
    }

    /**
     * Buffers one row, flushing immediately once the buffer reaches
     * `FLUSH_AT`. Past `BUFFER_CAP` new rows are dropped — stdout already
     * carried them — with one notice to stderr.
     */
    public function append(LogLine $line): void
    {
        if ($this->connection === null) {
            return;
        }

        if (count($this->buffer) >= $this->bufferCap) {
            $this->announceDrop();

            return;
        }

        $this->buffer[] = $line;

        if (count($this->buffer) >= self::FLUSH_AT) {
            $this->flush();
        }
    }

    /**
     * One transaction, one prepared multi-row INSERT per `FLUSH_AT`-row
     * chunk. A failure re-buffers the batch for the next append or the exit
     * flush to retry, and reports itself to stderr — never to the logger
     * this store mirrors.
     */
    public function flush(): void
    {
        $connection = $this->connection;

        if ($connection === null || $this->buffer === []) {
            return;
        }

        $batch = $this->buffer;
        $this->buffer = [];

        try {
            $this->insertBatch($connection, $batch);
            $this->dropAnnounced = false;
        } catch (Throwable $e) {
            $this->buffer = [...$batch, ...$this->buffer];
            $this->reportFailure($e);
        }
    }

    /**
     * Deletes every stored row whose `ts` is strictly before `$cutoff`, in
     * `$batchSize`-row batches looped until none change, then hands freed
     * pages back with `incremental_vacuum`. A disabled store prunes
     * nothing. Unlike `append()`/`flush()`, a failure here is not
     * swallowed — `orders:sweep` decides what a failed prune means for its
     * exit code.
     */
    public function prune(DateTimeImmutable $cutoff, int $batchSize = self::PRUNE_BATCH): int
    {
        if ($this->connection === null) {
            return 0;
        }

        // PDO::ATTR_ERRMODE_EXCEPTION (set in open()) turns a failing prepare()'s
        // default false return into a thrown exception, so the statement here is
        // always real.
        /** @var PDOStatement $delete */
        $delete = $this->connection->prepare(
            'DELETE FROM log_lines WHERE id IN (SELECT id FROM log_lines WHERE ts < ? LIMIT ?)',
        );

        $cutoffTs = $cutoff->setTimezone(new DateTimeZone('UTC'))->format(self::TIMESTAMP);
        $deleted = 0;

        do {
            $delete->execute([$cutoffTs, $batchSize]);
            $changed = $delete->rowCount();
            $deleted += $changed;
        } while ($changed > 0);

        $this->connection->exec('PRAGMA incremental_vacuum('.self::VACUUM_PAGES.')');

        return $deleted;
    }

    /**
     * @param  list<LogLine>  $batch
     */
    private function insertBatch(PDO $connection, array $batch): void
    {
        $connection->exec('BEGIN IMMEDIATE');

        try {
            foreach (array_chunk($batch, self::FLUSH_AT) as $chunk) {
                $statement = $this->preparedInsert($connection, count($chunk));
                $statement->execute(array_merge(...array_map(
                    fn (LogLine $line): array => $line->columns(),
                    $chunk,
                )));
            }
            $connection->exec('COMMIT');
        } catch (Throwable $e) {
            // A ROLLBACK failing here (no transaction left to roll back —
            // SQLite already auto-rolled back a fatal error) propagates its
            // own exception in $e's place; either way flush()'s catch
            // contains it and re-buffers the batch.
            $connection->exec('ROLLBACK');

            throw $e;
        }
    }

    private function preparedInsert(PDO $connection, int $rows): PDOStatement
    {
        if (isset($this->insertStatements[$rows])) {
            return $this->insertStatements[$rows];
        }

        $placeholders = implode(', ', array_fill(0, $rows, self::ROW_PLACEHOLDERS));
        $sql = 'INSERT INTO log_lines ('.self::INSERT_COLUMNS.') VALUES '.$placeholders;

        // PDO::ATTR_ERRMODE_EXCEPTION (set in open()) turns a failing prepare()'s
        // default false return into a thrown exception, so the statement here is
        // always real.
        /** @var PDOStatement $statement */
        $statement = $connection->prepare($sql);

        return $this->insertStatements[$rows] = $statement;
    }

    private function announceDrop(): void
    {
        if ($this->dropAnnounced) {
            return;
        }

        $this->dropAnnounced = true;
        $this->reportFailure(new RuntimeException(
            sprintf('buffer full at %d rows; dropping new rows until a flush succeeds', $this->bufferCap),
        ));
    }

    /**
     * The store never logs through the Story/Log pipeline it mirrors — its
     * own failures go to stderr, since stdout is the docs/spec.md §2
     * surface — so it cannot feed itself.
     */
    private function reportFailure(Throwable $e): void
    {
        try {
            ($this->stderrWriter)("log store: {$e->getMessage()}\n");
        } catch (Throwable) {
            // Nothing left to tell and nobody to throw at.
        }
    }

    /**
     * One §2-shaped `app.log` warn line written directly to stdout, bypassing
     * the logger entirely: the app boots, the store sits this process out,
     * and the operator can read why.
     */
    private static function warnDisabled(Closure $stdoutWriter, string $file, Throwable $e): void
    {
        $line = json_encode([
            'ts' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(self::TIMESTAMP),
            'level' => 'warn',
            'event' => 'app.log',
            'msg' => "⚠️ log store disabled: {$e->getMessage()}",
            'data' => ['log_database_file' => $file],
        ]);

        try {
            $stdoutWriter(($line === false ? '{}' : $line)."\n");
        } catch (Throwable) {
            // Even a refusing stdout must not turn a degraded store into a crash.
        }
    }

    /**
     * Ensure-on-open schema, versioned by `PRAGMA user_version`.
     * `BEGIN IMMEDIATE` plus `IF NOT EXISTS` makes a simultaneous
     * server-and-CLI first boot safe: one process bootstraps, the other
     * waits out the busy timeout and finds the schema already there. A file
     * ahead of this build throws, which disables the store for the process.
     */
    private static function ensureSchema(PDO $connection): void
    {
        $version = self::userVersion($connection);

        if ($version > self::SCHEMA_VERSION) {
            throw new RuntimeException("the file is at schema version {$version}; this build reads ".self::SCHEMA_VERSION);
        }

        if ($version === self::SCHEMA_VERSION) {
            return;
        }

        $connection->exec('BEGIN IMMEDIATE');

        try {
            $connection->exec(self::DDL);
            $connection->exec('PRAGMA user_version = '.self::SCHEMA_VERSION);
            $connection->exec('COMMIT');
        } catch (Throwable $e) {
            // A ROLLBACK failing here propagates its own exception in $e's
            // place; either way open()'s catch disables the store.
            $connection->exec('ROLLBACK');

            throw $e;
        }
    }

    private static function userVersion(PDO $connection): int
    {
        // PDO::ATTR_ERRMODE_EXCEPTION (set in open()) turns a failing query()'s
        // default false return into a thrown exception, so the statement here is
        // always real.
        /** @var PDOStatement $statement */
        $statement = $connection->query('PRAGMA user_version');
        $value = $statement->fetchColumn();

        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * Writes to the named stream, opened per write: the `STDOUT`/`STDERR`
     * constants only exist in the CLI SAPI, and the built-in web server's
     * workers are `cli-server` — referencing them there is a fatal error
     * before `open()`'s guard can catch anything.
     */
    private static function defaultWriter(string $target): Closure
    {
        return function (string $chunk) use ($target): void {
            $stream = fopen($target, 'w');

            if ($stream !== false) {
                fwrite($stream, $chunk);
                fclose($stream);
            }
        };
    }
}
