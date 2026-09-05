<?php

declare(strict_types=1);

namespace App\Console\Commands\Sweep;

use App\Logging\LogStore;
use Illuminate\Testing\PendingCommand;
use RuntimeException;
use Tests\LogStoreFixtures as Fixtures;

/**
 * `$this->artisan()` hands back an exit code when console output is not mocked
 * and a pending command when it is. These tests assert on output, so they run
 * the command through the pending command.
 */
$pending = fn (PendingCommand|int $command): PendingCommand => $command instanceof PendingCommand
    ? $command
    : throw new RuntimeException('Console output is not mocked, so the command ran instead of pending.');

it('fails clearly on a garbage --as-of value', function () use ($pending): void {
    $pending($this->artisan('sweep:logs', ['--as-of' => 'yesterdayish']))
        ->expectsOutputToContain('is not a date the sweep can run as of')
        ->assertFailed();
});

it('skips the log retention prune silently when the store is disabled', function () use ($pending): void {
    $this->app->instance(LogStore::class, LogStore::open('off'));

    $pending($this->artisan('sweep:logs'))
        ->assertSuccessful();
});

it('prunes stored log lines older than LOG_RETENTION_DAYS, as of the sweep date', function () use ($pending): void {
    config(['log_store.retention_days' => 14]);
    $store = LogStore::open(Fixtures::tempFile());
    $this->app->instance(LogStore::class, $store);

    $store->append(Fixtures::line('order.place', '2026-07-01T00:00:00.000Z'));
    $store->append(Fixtures::line('order.place', '2026-08-20T00:00:00.000Z'));
    $store->flush();

    $pending($this->artisan('sweep:logs', ['--as-of' => '2026-08-24']))
        ->assertSuccessful();

    $connection = Fixtures::connectionOrFail($store);
    expect(Fixtures::rowCount($connection))->toBe(1)
        ->and((string) Fixtures::scalar($connection, 'SELECT ts FROM log_lines'))->toBe('2026-08-20T00:00:00.000Z');
});

it('skips the prune silently when LOG_RETENTION_DAYS is off, even with a working store', function () use ($pending): void {
    config(['log_store.retention_days' => null]);
    $store = LogStore::open(Fixtures::tempFile());
    $this->app->instance(LogStore::class, $store);

    $store->append(Fixtures::line('order.place', '2020-01-01T00:00:00.000Z'));
    $store->flush();

    $pending($this->artisan('sweep:logs'))
        ->assertSuccessful();

    expect(Fixtures::rowCount(Fixtures::connectionOrFail($store)))->toBe(1);
});

it('fails the command on a prune failure', function () use ($pending): void {
    config(['log_store.retention_days' => 14]);
    $store = LogStore::open(Fixtures::tempFile());
    Fixtures::connectionOrFail($store)->exec('DROP TABLE log_lines');
    $this->app->instance(LogStore::class, $store);

    $pending($this->artisan('sweep:logs'))
        ->expectsOutputToContain('log retention prune failed')
        ->assertFailed();
});
