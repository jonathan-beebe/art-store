<?php

declare(strict_types=1);

namespace App\Console\Commands\Sweep;

use App\Models\Customer;
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

it('deletes the anonymous customers that sat idle past the retention window', function () use ($pending): void {
    config(['customers.anonymous_retention_days' => 30]);
    Date::setTestNow('2026-08-01 00:00:00');
    $customer = Customer::factory()->anonymous()->create(['created_at' => $this->moment('2026-07-01 00:00:00')]);

    $pending($this->artisan('sweep:customers'))
        ->expectsOutputToContain('Deleting anonymous customers idle for 30 days')
        ->expectsOutputToContain('1 anonymous customer(s) deleted.')
        ->assertSuccessful();

    expect(Customer::find($customer->id))->toBeNull();
});

it('says so when nothing has been idle that long', function () use ($pending): void {
    config(['customers.anonymous_retention_days' => 30]);
    Date::setTestNow('2026-08-01 00:00:00');

    $pending($this->artisan('sweep:customers'))
        ->expectsOutputToContain('No anonymous customer has been idle that long.')
        ->assertSuccessful();
});

it('sweeps as of a given --as-of date rather than the application clock', function () use ($pending): void {
    config(['customers.anonymous_retention_days' => 30]);
    Date::setTestNow('2026-08-21 10:00:00');
    $customer = Customer::factory()->anonymous()->create(['created_at' => $this->moment('2026-07-01 00:00:00')]);

    $pending($this->artisan('sweep:customers', ['--as-of' => '2026-08-01 12:00:00']))
        ->expectsOutputToContain('1 anonymous customer(s) deleted.')
        ->assertSuccessful();

    expect(Customer::find($customer->id))->toBeNull();
});

it('fails clearly on a garbage --as-of value, sweeping nothing', function () use ($pending): void {
    config(['customers.anonymous_retention_days' => 30]);
    $customer = Customer::factory()->anonymous()->create(['created_at' => $this->moment('2020-01-01 00:00:00')]);

    $pending($this->artisan('sweep:customers', ['--as-of' => 'yesterdayish']))
        ->expectsOutputToContain('is not a date the sweep can run as of')
        ->assertFailed();

    expect(Customer::find($customer->id))->not->toBeNull();
});

it('skips the sweep silently when ANONYMOUS_CUSTOMER_RETENTION_DAYS is off', function () use ($pending): void {
    config(['customers.anonymous_retention_days' => null]);
    $customer = Customer::factory()->anonymous()->create(['created_at' => $this->moment('2020-01-01 00:00:00')]);

    $pending($this->artisan('sweep:customers'))
        ->assertSuccessful();

    expect(Customer::find($customer->id))->not->toBeNull();
});
