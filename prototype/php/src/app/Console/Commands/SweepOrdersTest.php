<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Orders\OrderStatus;
use App\Logging\LogStore;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Schema;
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

it('cancels the orders that sat past the cutoff and prints them', function () use ($pending): void {
    Date::setTestNow('2026-08-21 10:00:00');
    $order = $this->orderFor($this->anonymousCustomer(), $this->listing($this->seller()));
    $order->update(['placed_at' => $this->moment('2026-08-20 09:00:00')]);

    $pending($this->artisan('orders:sweep'))
        ->expectsOutputToContain('Cancelling orders left unverified for 24 hours')
        ->expectsOutputToContain($order->id)
        ->expectsOutputToContain('1 order(s) cancelled.')
        ->assertSuccessful();

    expect($order->fresh()?->status)->toBe(OrderStatus::Cancelled);
});

it('says so when nothing has been waiting that long', function () use ($pending): void {
    Date::setTestNow('2026-08-21 10:00:00');

    $pending($this->artisan('orders:sweep'))
        ->expectsOutputToContain('No order has been waiting that long.')
        ->assertSuccessful();
});

it('sweeps as of a given --as-of date rather than the application clock', function () use ($pending): void {
    Date::setTestNow('2026-08-21 10:00:00');
    $order = $this->orderFor($this->anonymousCustomer(), $this->listing($this->seller()));
    $order->update(['placed_at' => $this->moment('2026-07-01 09:00:00')]);

    $pending($this->artisan('orders:sweep', ['--as-of' => '2026-08-01 12:00:00']))
        ->expectsOutputToContain('1 order(s) cancelled.')
        ->assertSuccessful();

    expect($order->fresh()?->status)->toBe(OrderStatus::Cancelled);
});

it('fails clearly on a garbage --as-of value, sweeping nothing', function () use ($pending): void {
    $order = $this->orderFor($this->anonymousCustomer(), $this->listing($this->seller()));
    $order->update(['placed_at' => $this->moment('2020-01-01 00:00:00')]);

    $pending($this->artisan('orders:sweep', ['--as-of' => 'yesterdayish']))
        ->expectsOutputToContain('is not a date the sweep can run as of')
        ->assertFailed();

    expect($order->fresh()?->status)->toBe(OrderStatus::PendingVerification);
});

it('skips the log retention prune silently when the store is disabled', function () use ($pending): void {
    $this->app->instance(LogStore::class, LogStore::open('off'));

    $pending($this->artisan('orders:sweep'))
        ->assertSuccessful();
});

it('prunes stored log lines older than LOG_RETENTION_DAYS, as of the sweep date', function () use ($pending): void {
    config(['log_store.retention_days' => 14]);
    $store = LogStore::open(Fixtures::tempFile());
    $this->app->instance(LogStore::class, $store);

    $store->append(Fixtures::line('order.place', '2026-07-01T00:00:00.000Z'));
    $store->append(Fixtures::line('order.place', '2026-08-20T00:00:00.000Z'));
    $store->flush();

    $pending($this->artisan('orders:sweep', ['--as-of' => '2026-08-24']))
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

    $pending($this->artisan('orders:sweep'))
        ->assertSuccessful();

    expect(Fixtures::rowCount(Fixtures::connectionOrFail($store)))->toBe(1);
});

it('fails the command on an order-sweep failure but still prunes the log store', function () use ($pending): void {
    Schema::drop('orders');

    config(['log_store.retention_days' => 14]);
    $store = LogStore::open(Fixtures::tempFile());
    $this->app->instance(LogStore::class, $store);

    $store->append(Fixtures::line('order.place', '2020-01-01T00:00:00.000Z'));
    $store->flush();

    $pending($this->artisan('orders:sweep', ['--as-of' => '2026-08-24']))
        ->expectsOutputToContain('order sweep failed')
        ->assertFailed();

    expect(Fixtures::rowCount(Fixtures::connectionOrFail($store)))->toBe(0);
});

it('fails the command on a prune failure but leaves the stale-order sweep standing', function () use ($pending): void {
    Date::setTestNow('2026-08-21 10:00:00');
    $order = $this->orderFor($this->anonymousCustomer(), $this->listing($this->seller()));
    $order->update(['placed_at' => $this->moment('2026-08-20 09:00:00')]);

    config(['log_store.retention_days' => 14]);
    $store = LogStore::open(Fixtures::tempFile());
    Fixtures::connectionOrFail($store)->exec('DROP TABLE log_lines');
    $this->app->instance(LogStore::class, $store);

    $pending($this->artisan('orders:sweep'))
        ->expectsOutputToContain('1 order(s) cancelled.')
        ->expectsOutputToContain('log retention prune failed')
        ->assertFailed();

    expect($order->fresh()?->status)->toBe(OrderStatus::Cancelled);
});
