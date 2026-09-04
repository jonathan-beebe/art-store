<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Fulfillment\ConfirmDelivered;
use App\Actions\Fulfillment\MarkShipped;
use App\Actions\Orders\FinalizeOrder;
use App\Models\Payout;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\PendingCommand;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

/**
 * `$this->artisan()` hands back an exit code when console output is not mocked
 * and a pending command when it is. These tests assert on output, so they run
 * the command through the pending command.
 */
$pending = fn (PendingCommand|int $command): PendingCommand => $command instanceof PendingCommand
    ? $command
    : throw new RuntimeException('Console output is not mocked, so the command ran instead of pending.');

it('pays the week that ended before the given date', function () use ($pending): void {
    $seller = $this->seller();
    $order = app(FinalizeOrder::class)(
        $this->orderFor($this->verifiedCustomer(), $this->listing($seller, ['price_cents' => 45000])),
        '4242 4242 4242 4242',
        $this->moment('2026-08-20 10:00:00'),
    );
    $fulfillment = app(MarkShipped::class)($order->fulfillments()->sole(), 'USPS', '9400111899', $this->moment('2026-08-20 11:00:00'));
    app(ConfirmDelivered::class)($fulfillment, $this->moment('2026-08-21 11:00:00'));

    $pending($this->artisan('payouts:run', ['--as-of' => '2026-08-24']))
        ->expectsOutputToContain('2026-08-17 to 2026-08-23')
        ->expectsOutputToContain('Blue Kiln Studio $405.00')
        ->expectsOutputToContain('1 seller(s) paid.')
        ->assertSuccessful();

    expect(Payout::query()->sole()->amount_cents)->toBe(40500);
});

it('reports a week with nothing to pay', function () use ($pending): void {
    $pending($this->artisan('payouts:run', ['--as-of' => '2026-08-24']))
        ->expectsOutputToContain('No seller has a released balance')
        ->assertSuccessful();
});

it('fails clearly on a garbage --as-of value', function () use ($pending): void {
    $pending($this->artisan('payouts:run', ['--as-of' => 'yesterdayish']))
        ->expectsOutputToContain('is not a date payouts can settle as of')
        ->assertFailed();
});

it('settles as of the application clock when --as-of is omitted', function () use ($pending): void {
    $this->travelTo($this->moment('2026-08-24 09:00:00'));

    $pending($this->artisan('payouts:run'))
        ->expectsOutputToContain('2026-08-17 to 2026-08-23')
        ->assertSuccessful();
});

it('lets an exception from the payout action escape the command uncaught, unlike orders:sweep', function (): void {
    Schema::drop('ledger_entries');

    $command = app(RunWeeklyPayouts::class);
    $command->setLaravel($this->app);

    expect(fn () => $command->run(new ArrayInput(['--as-of' => '2026-08-24']), new NullOutput))
        ->toThrow(QueryException::class);
});

it('names every paid seller', function () use ($pending): void {
    foreach (['Blue Kiln Studio', 'Rye Press'] as $shopName) {
        $seller = $this->seller($shopName);
        $order = app(FinalizeOrder::class)(
            $this->orderFor($this->verifiedCustomer(), $this->listing($seller, ['price_cents' => 10000])),
            '4242 4242 4242 4242',
            $this->moment('2026-08-20 10:00:00'),
        );
        $fulfillment = app(MarkShipped::class)($order->fulfillments()->sole(), 'USPS', '94001', $this->moment('2026-08-20 11:00:00'));
        app(ConfirmDelivered::class)($fulfillment, $this->moment('2026-08-21 11:00:00'));
    }

    $pending($this->artisan('payouts:run', ['--as-of' => '2026-08-24']))
        ->expectsOutputToContain('Blue Kiln Studio $90.00')
        ->expectsOutputToContain('Rye Press $90.00')
        ->expectsOutputToContain('2 seller(s) paid.')
        ->assertSuccessful();
});
