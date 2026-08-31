<?php

declare(strict_types=1);

namespace App\Logging;

use Closure;
use DateTimeImmutable;
use PDO;
use PDOException;
use RuntimeException;
use Tests\LogStoreFixtures as Fixtures;

/**
 * These tests build `LogStore` directly against a temp file and its
 * `stdoutWriter`/`stderrWriter`/`registerShutdown` seams — never through the
 * `stdout` channel's tap, and never through `Tests\CapturedStory`, which
 * swaps the whole logger and bypasses the tap entirely.
 * `App\Logging\LogStoreHandlerTest` and `LogStoreTapTest` cover the ingest
 * path that feeds this class.
 */
it('bootstraps the schema on first open, versioned by user_version', function (): void {
    $store = LogStore::open(Fixtures::tempFile());
    $connection = Fixtures::connectionOrFail($store);

    expect((int) Fixtures::scalar($connection, 'PRAGMA user_version'))->toBe(1)
        ->and((int) Fixtures::scalar($connection, 'PRAGMA auto_vacuum'))->toBe(2)
        ->and((string) Fixtures::scalar($connection, 'PRAGMA journal_mode'))->toBe('wal')
        ->and((int) Fixtures::scalar($connection, 'PRAGMA synchronous'))->toBe(1)
        ->and((int) Fixtures::scalar($connection, 'PRAGMA busy_timeout'))->toBe(250);

    $tables = Fixtures::column($connection, "SELECT name FROM sqlite_master WHERE type = 'table'");
    $indexes = Fixtures::column($connection, "SELECT name FROM sqlite_master WHERE type = 'index'");

    expect($tables)->toContain('log_lines')
        ->and($indexes)->toEqualCanonicalizing([
            'log_lines_ts', 'log_lines_event_ts', 'log_lines_level_ts',
            'log_lines_request_id', 'log_lines_txn_id', 'log_lines_actor_id',
        ]);
});

it('is idempotent across two opens against the same file', function (): void {
    $file = Fixtures::tempFile();
    $first = LogStore::open($file);
    $second = LogStore::open($file);
    $firstConnection = Fixtures::connectionOrFail($first);

    $second->append(Fixtures::line());
    $second->flush();

    expect(Fixtures::rowCount($firstConnection))->toBe(1);
});

it('disables the store for the literal "off", without writing anything', function (): void {
    $stdout = [];
    $store = LogStore::open('off', stdoutWriter: function (string $chunk) use (&$stdout): void {
        $stdout[] = $chunk;
    });

    expect($store->connection)->toBeNull()
        ->and($stdout)->toBe([]);
});

it('never registers an exit flush for a disabled store', function (): void {
    $registered = false;
    LogStore::open('off', registerShutdown: function () use (&$registered): void {
        $registered = true;
    });

    expect($registered)->toBeFalse();
});

it('disables the store and warns on stdout when the file cannot be opened', function (): void {
    $stdout = [];
    $store = LogStore::open('/nonexistent-log-store-test-dir/db.sqlite3', stdoutWriter: function (string $chunk) use (&$stdout): void {
        $stdout[] = $chunk;
    });

    expect($store->connection)->toBeNull()
        ->and($stdout)->toHaveCount(1);

    /** @var array<string, mixed> $warning */
    $warning = json_decode($stdout[0], true, flags: JSON_THROW_ON_ERROR);

    expect($warning['level'])->toBe('warn')
        ->and($warning['event'])->toBe('app.log')
        ->and($warning['msg'])->toStartWith('⚠️ log store disabled:')
        ->and($warning['data'])->toBe(['log_database_file' => '/nonexistent-log-store-test-dir/db.sqlite3']);
});

it('writes the disabled warning straight to stdout when no writer is injected', function (): void {
    $store = LogStore::open('/nonexistent-log-store-test-dir/db.sqlite3');

    expect($store->connection)->toBeNull();
});

it('never crashes open() even when the injected stdout writer itself throws', function (): void {
    $store = LogStore::open(
        '/nonexistent-log-store-test-dir/db.sqlite3',
        stdoutWriter: function (): void {
            throw new RuntimeException('stdout refuses this chunk');
        },
    );

    expect($store->connection)->toBeNull();
});

it("disables the store when the file is ahead of this build's schema version", function (): void {
    $file = Fixtures::tempFile();
    $bootstrapped = LogStore::open($file);
    $connection = Fixtures::connectionOrFail($bootstrapped);
    $connection->exec('PRAGMA user_version = 2');

    $stdout = [];
    $store = LogStore::open($file, stdoutWriter: function (string $chunk) use (&$stdout): void {
        $stdout[] = $chunk;
    });

    expect($store->connection)->toBeNull();

    /** @var array<string, mixed> $warning */
    $warning = json_decode($stdout[0], true, flags: JSON_THROW_ON_ERROR);
    expect($warning['msg'])->toContain('schema version 2');
});

it('rolls back and disables the store when the bootstrap DDL fails partway through', function (): void {
    $file = Fixtures::tempFile();
    $seed = new PDO('sqlite:'.$file);
    // Occupies the name the DDL's first index needs, under a type
    // `CREATE INDEX IF NOT EXISTS` does not treat as already satisfied.
    $seed->exec('CREATE TABLE log_lines_ts (id INTEGER)');

    $store = LogStore::open($file, stdoutWriter: function (): void {});

    expect($store->connection)->toBeNull()
        ->and((int) Fixtures::scalar($seed, 'PRAGMA user_version'))->toBe(0);
});

it('buffers appended rows and flushes automatically at the row cap', function (): void {
    $store = LogStore::open(Fixtures::tempFile());
    $connection = Fixtures::connectionOrFail($store);

    for ($i = 0; $i < 255; $i++) {
        $store->append(Fixtures::line());
    }

    expect(Fixtures::rowCount($connection))->toBe(0);

    $store->append(Fixtures::line());

    expect(Fixtures::rowCount($connection))->toBe(256);
});

it('flushes whatever is buffered on demand, below the row cap', function (): void {
    $store = LogStore::open(Fixtures::tempFile());
    $connection = Fixtures::connectionOrFail($store);

    for ($i = 0; $i < 5; $i++) {
        $store->append(Fixtures::line());
    }

    expect(Fixtures::rowCount($connection))->toBe(0);

    $store->flush();

    expect(Fixtures::rowCount($connection))->toBe(5);
});

it('runs the exit flush registered with registerShutdown', function (): void {
    $captured = null;
    $store = LogStore::open(Fixtures::tempFile(), registerShutdown: function (Closure $callback) use (&$captured): void {
        $captured = $callback;
    });
    $connection = Fixtures::connectionOrFail($store);

    $store->append(Fixtures::line());
    expect(Fixtures::rowCount($connection))->toBe(0)
        ->and($captured)->not->toBeNull();

    ($captured ?? throw new RuntimeException('registerShutdown was never called'))();

    expect(Fixtures::rowCount($connection))->toBe(1);
});

it('append(), flush(), and prune() are no-ops for a disabled store', function (): void {
    $store = LogStore::open('off');

    $store->append(Fixtures::line());
    $store->flush();

    expect($store->prune(new DateTimeImmutable))->toBe(0);
});

it('re-buffers a failed batch, reports it to stderr, and inserts it on the next successful flush', function (): void {
    $file = Fixtures::tempFile();
    $stderr = [];
    $store = LogStore::open($file, stderrWriter: function (string $chunk) use (&$stderr): void {
        $stderr[] = $chunk;
    });
    $connection = Fixtures::connectionOrFail($store);
    $connection->exec('DROP TABLE log_lines');

    $store->append(Fixtures::line('order.place'));
    $store->flush();

    expect($stderr)->toHaveCount(1)
        ->and($stderr[0])->toStartWith('log store:');

    $connection->exec(Fixtures::LOG_LINES_TABLE_SQL);

    $store->flush();

    expect(Fixtures::rowCount($connection))->toBe(1);
});

it('drops rows past the buffer cap and reports exactly one notice to stderr', function (): void {
    $file = Fixtures::tempFile();
    $stderr = [];
    $store = LogStore::open($file, stderrWriter: function (string $chunk) use (&$stderr): void {
        $stderr[] = $chunk;
    });
    $connection = Fixtures::connectionOrFail($store);
    $connection->exec('DROP TABLE log_lines');

    for ($i = 0; $i < 10_050; $i++) {
        $store->append(Fixtures::line());
    }

    $dropNotices = array_values(array_filter($stderr, fn (string $m): bool => str_contains($m, 'buffer full')));
    expect($dropNotices)->toHaveCount(1);

    $connection->exec(Fixtures::LOG_LINES_TABLE_SQL);

    $store->flush();

    expect(Fixtures::rowCount($connection))->toBe(10_000);
});

it('never crashes flush() even when the injected stderr writer itself throws', function (): void {
    $store = LogStore::open(Fixtures::tempFile(), stderrWriter: function (): void {
        throw new RuntimeException('stderr refuses this chunk');
    });
    $connection = Fixtures::connectionOrFail($store);
    $connection->exec('DROP TABLE log_lines');

    $store->append(Fixtures::line());
    $store->flush();

    expect($store->connection)->not->toBeNull();
});

it('deletes rows before the cutoff in batches, looping until none change', function (): void {
    $store = LogStore::open(Fixtures::tempFile());
    $connection = Fixtures::connectionOrFail($store);

    foreach (['2026-08-01', '2026-08-02', '2026-08-03', '2026-08-10', '2026-08-11'] as $day) {
        $store->append(Fixtures::line('order.place', "{$day}T00:00:00.000Z"));
    }
    $store->flush();

    $deleted = $store->prune(new DateTimeImmutable('2026-08-05T00:00:00Z'), batchSize: 2);

    expect($deleted)->toBe(3)
        ->and(Fixtures::rowCount($connection))->toBe(2);
});

it('prunes nothing when every stored row is at or after the cutoff', function (): void {
    $store = LogStore::open(Fixtures::tempFile());
    $connection = Fixtures::connectionOrFail($store);
    $store->append(Fixtures::line('order.place', '2026-08-10T00:00:00.000Z'));
    $store->flush();

    $deleted = $store->prune(new DateTimeImmutable('2026-08-05T00:00:00Z'));

    expect($deleted)->toBe(0)
        ->and(Fixtures::rowCount($connection))->toBe(1);
});

it('lets a prune failure propagate, rather than swallowing it like append()/flush() do', function (): void {
    $store = LogStore::open(Fixtures::tempFile());
    $connection = Fixtures::connectionOrFail($store);
    $connection->exec('DROP TABLE log_lines');

    expect(fn () => $store->prune(new DateTimeImmutable))->toThrow(PDOException::class);
});
