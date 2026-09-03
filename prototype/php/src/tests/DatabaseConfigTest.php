<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

/**
 * config/database.php promises the app's file-backed sqlite the same WAL
 * trio the log store sets for itself (app/Logging/LogStore.php). The
 * suite's own `:memory:` connection cannot witness that promise — a memory
 * database ignores the journal_mode switch — so this test opens a real
 * file through the same connection config and reads the pragmas back.
 */
it('runs a file-backed app database in wal mode with the configured pragmas', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'wal-check-');

    config()->set('database.connections.wal-check', array_replace(
        (array) config('database.connections.sqlite'),
        ['database' => $path, 'url' => null],
    ));

    try {
        $connection = DB::connection('wal-check');

        $journal = $connection->selectOne('PRAGMA journal_mode');
        $synchronous = $connection->selectOne('PRAGMA synchronous');
        $busy = $connection->selectOne('PRAGMA busy_timeout');
        assert($journal instanceof stdClass && $synchronous instanceof stdClass && $busy instanceof stdClass);

        expect($journal->journal_mode)->toBe('wal')
            ->and($synchronous->synchronous)->toEqual(1)
            ->and($busy->timeout)->toEqual(5000);
    } finally {
        DB::purge('wal-check');

        foreach ([$path, $path.'-wal', $path.'-shm'] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
});

/**
 * config/database.php promises the analytics connection its own WAL mode,
 * a short busy timeout, and synchronous writes turned off. The suite's
 * `:memory:` analytics connection cannot witness that promise either, so
 * this test opens a real file through the connection's own config.
 */
it('runs the analytics database in wal mode with a short busy timeout and synchronous off', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'analytics-wal-check-');

    config()->set('database.connections.analytics-wal-check', array_replace(
        (array) config('database.connections.analytics'),
        ['database' => $path, 'url' => null],
    ));

    try {
        $connection = DB::connection('analytics-wal-check');

        $journal = $connection->selectOne('PRAGMA journal_mode');
        $synchronous = $connection->selectOne('PRAGMA synchronous');
        $busy = $connection->selectOne('PRAGMA busy_timeout');
        assert($journal instanceof stdClass && $synchronous instanceof stdClass && $busy instanceof stdClass);

        expect($journal->journal_mode)->toBe('wal')
            ->and($synchronous->synchronous)->toEqual(0)
            ->and($busy->timeout)->toEqual(250);
    } finally {
        DB::purge('analytics-wal-check');

        foreach ([$path, $path.'-wal', $path.'-shm'] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
});
