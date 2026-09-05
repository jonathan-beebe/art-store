<?php

declare(strict_types=1);

namespace App\Seller;

use App\Actions\Escrow\RunWeeklyPayout;
use App\Actions\Fulfillment\DeclineFulfillment;
use App\Domain\Seller\PeriodFigures;
use App\Domain\Seller\PeriodPayoutStatus;

it('opens an eight-period window ending with the period in progress', function (): void {
    $seller = $this->seller();

    $periods = EarningsPeriods::for($seller, $this->moment('2026-08-19 09:00:00'));

    expect($periods->periods)->toHaveCount(8)
        ->and($periods->current()->period->start->format('Y-m-d'))->toBe('2026-08-17')
        ->and($periods->past())->toHaveCount(7)
        ->and($periods->past()[0]->period->start->format('Y-m-d'))->toBe('2026-08-10');
});

it('sums gross sales and fees into the period the order was placed in', function (): void {
    $seller = $this->seller();
    $fulfillment = $this->paidFulfillmentFor($seller, priceCents: 10_000);
    $fulfillment->order()->update(['placed_at' => $this->moment('2026-08-18 10:00:00')]);

    $periods = EarningsPeriods::for($seller, $this->moment('2026-08-19 09:00:00'));

    expect($periods->current()->orderCount)->toBe(1)
        ->and($periods->current()->sales->format())->toBe('$100.00')
        ->and($periods->current()->fees->format())->toBe('$10.00');
});

it('leaves out an order that never paid — its fulfillment exists at awaiting_shipment before a card is charged', function (): void {
    $seller = $this->seller();
    $unpaid = $this->orderFor($this->verifiedCustomer(), $this->listing($seller, ['price_cents' => 10_000]));
    $unpaid->update(['placed_at' => $this->moment('2026-08-18 10:00:00')]);

    $periods = EarningsPeriods::for($seller, $this->moment('2026-08-19 09:00:00'));

    expect($periods->current()->orderCount)->toBe(0)
        ->and($periods->current()->sales->isZero())->toBeTrue();
});

it('keeps a declined order in sales and fees, and nets it back through its own refund', function (): void {
    $seller = $this->seller();
    $fulfillment = $this->paidFulfillmentFor($seller, priceCents: 10_000);
    $fulfillment->order()->update(['placed_at' => $this->moment('2026-08-18 10:00:00')]);
    app(DeclineFulfillment::class)($fulfillment->refresh(), 'Out of stock.', $this->moment('2026-08-18 11:00:00'));

    $periods = EarningsPeriods::for($seller, $this->moment('2026-08-19 09:00:00'));

    expect($periods->current()->orderCount)->toBe(1)
        ->and($periods->current()->sales->format())->toBe('$100.00')
        ->and($periods->current()->fees->format())->toBe('$10.00')
        ->and($periods->current()->refunds->format())->toBe('$90.00')
        ->and($periods->current()->net()->isZero())->toBeTrue();
});

it('dates a refund by when it happened, and keeps the sale it undoes in the period it was placed', function (): void {
    $seller = $this->seller();
    $fulfillment = $this->paidFulfillmentFor($seller, priceCents: 10_000);
    $fulfillment->order()->update(['placed_at' => $this->moment('2026-08-11 10:00:00')]);
    app(DeclineFulfillment::class)($fulfillment->refresh(), 'Buyer asked to cancel.', $this->moment('2026-08-18 11:00:00'));

    $periods = EarningsPeriods::for($seller, $this->moment('2026-08-19 09:00:00'));
    $saleWeek = collect($periods->past())->sole(fn (PeriodFigures $f): bool => $f->period->start->format('Y-m-d') === '2026-08-10');

    expect($saleWeek->orderCount)->toBe(1)
        ->and($saleWeek->sales->format())->toBe('$100.00')
        ->and($saleWeek->refunds->isZero())->toBeTrue()
        ->and($periods->current()->sales->isZero())->toBeTrue()
        ->and($periods->current()->refunds->format())->toBe('$90.00');
});

it('reads the current period\'s sales change against the period right before it', function (): void {
    $seller = $this->seller();
    $previous = $this->paidFulfillmentFor($seller, priceCents: 10_000);
    $previous->order()->update(['placed_at' => $this->moment('2026-08-11 10:00:00')]);
    $current = $this->paidFulfillmentFor($seller, priceCents: 15_000);
    $current->order()->update(['placed_at' => $this->moment('2026-08-18 10:00:00')]);

    $periods = EarningsPeriods::for($seller, $this->moment('2026-08-19 09:00:00'));

    expect($periods->currentSalesChange()->text)->toBe('+50.0%');
});

it('reads the period in progress as in-progress regardless of any payout row', function (): void {
    $seller = $this->seller();

    $periods = EarningsPeriods::for($seller, $this->moment('2026-08-19 09:00:00'));

    expect($periods->settlementOf($periods->current())->status)->toBe(PeriodPayoutStatus::InProgress);
});

it('reads a completed period with a payout row as paid, and carries its date', function (): void {
    $seller = $this->seller();
    $this->deliveredFulfillmentFor(
        $seller,
        priceCents: 10_000,
        orderedAt: $this->moment('2026-08-11 09:00:00'),
        shippedAt: $this->moment('2026-08-11 12:00:00'),
        deliveredAt: $this->moment('2026-08-12 10:00:00'),
    );
    app(RunWeeklyPayout::class)($this->moment('2026-08-17 09:00:00'));

    $periods = EarningsPeriods::for($seller, $this->moment('2026-08-19 09:00:00'));
    $week = collect($periods->past())->sole(fn (PeriodFigures $f): bool => $f->period->start->format('Y-m-d') === '2026-08-10');

    expect($periods->settlementOf($week)->status)->toBe(PeriodPayoutStatus::Paid)
        ->and($periods->settlementOf($week)->paidAt?->format('Y-m-d'))->toBe('2026-08-17');
});

it('reads a completed period with no payout row as settled at zero', function (): void {
    $seller = $this->seller();

    $periods = EarningsPeriods::for($seller, $this->moment('2026-08-19 09:00:00'));

    expect($periods->settlementOf($periods->past()[0])->status)->toBe(PeriodPayoutStatus::None);
});

it('charts the window as one bar per period, even when every period is empty', function (): void {
    $seller = $this->seller();

    $periods = EarningsPeriods::for($seller, $this->moment('2026-08-19 09:00:00'));
    $strip = $periods->netStrip(160);

    expect($strip->bars)->toHaveCount(8)
        ->and($strip->baselinePx)->toBe(160);
});

it('names a loss period\'s bar with the minus sign Money::format() already carries, plus "a net loss"', function (): void {
    $seller = $this->seller();
    $fulfillment = $this->paidFulfillmentFor($seller, priceCents: 10_000, paidAt: $this->moment('2026-08-11 10:00:00'));
    $fulfillment->order()->update(['placed_at' => $this->moment('2026-08-11 10:00:00')]);
    // Refunded the following period: that period has the refund and no sale of its own, so its net is negative.
    app(DeclineFulfillment::class)($fulfillment->refresh(), 'Buyer changed their mind.', $this->moment('2026-08-18 10:00:00'));

    $periods = EarningsPeriods::for($seller, $this->moment('2026-08-19 09:00:00'));
    $lossBar = $periods->netStrip(160)->bars[count($periods->periods) - 1];

    expect($periods->current()->net()->cents)->toBeLessThan(0)
        ->and($lossBar->tip)->toContain('-$90.00')
        ->and($lossBar->tip)->toContain('a net loss')
        ->and($lossBar->negative)->toBeTrue();
});
