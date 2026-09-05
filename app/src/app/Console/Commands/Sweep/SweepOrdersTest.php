<?php

declare(strict_types=1);

namespace App\Console\Commands\Sweep;

use App\Domain\Orders\OrderStatus;
use Illuminate\Support\Facades\Date;
use Illuminate\Testing\PendingCommand;
use RuntimeException;

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

    $pending($this->artisan('sweep:orders'))
        ->expectsOutputToContain('Cancelling orders left unverified for 24 hours')
        ->expectsOutputToContain($order->id)
        ->expectsOutputToContain('1 order(s) cancelled.')
        ->assertSuccessful();

    expect($order->fresh()?->status)->toBe(OrderStatus::Cancelled);
});

it('says so when nothing has been waiting that long', function () use ($pending): void {
    Date::setTestNow('2026-08-21 10:00:00');

    $pending($this->artisan('sweep:orders'))
        ->expectsOutputToContain('No order has been waiting that long.')
        ->assertSuccessful();
});

it('sweeps as of a given --as-of date rather than the application clock', function () use ($pending): void {
    Date::setTestNow('2026-08-21 10:00:00');
    $order = $this->orderFor($this->anonymousCustomer(), $this->listing($this->seller()));
    $order->update(['placed_at' => $this->moment('2026-07-01 09:00:00')]);

    $pending($this->artisan('sweep:orders', ['--as-of' => '2026-08-01 12:00:00']))
        ->expectsOutputToContain('1 order(s) cancelled.')
        ->assertSuccessful();

    expect($order->fresh()?->status)->toBe(OrderStatus::Cancelled);
});

it('fails clearly on a garbage --as-of value, sweeping nothing', function () use ($pending): void {
    $order = $this->orderFor($this->anonymousCustomer(), $this->listing($this->seller()));
    $order->update(['placed_at' => $this->moment('2020-01-01 00:00:00')]);

    $pending($this->artisan('sweep:orders', ['--as-of' => 'yesterdayish']))
        ->expectsOutputToContain('is not a date the sweep can run as of')
        ->assertFailed();

    expect($order->fresh()?->status)->toBe(OrderStatus::PendingVerification);
});
