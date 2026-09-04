<?php

declare(strict_types=1);

namespace App\Seller;

use App\Actions\Escrow\RunWeeklyPayout;

it('estimates the next payout as the ledger balance available to pay out', function (): void {
    $seller = $this->seller();
    $this->deliveredFulfillmentFor($seller, priceCents: 10_000, deliveredAt: $this->moment('2026-08-19 10:00:00'));

    $payout = NextPayout::for($seller, $this->moment('2026-08-20 09:00:00'));

    expect($payout->estimate->amount->equals($seller->refresh()->escrowBalance()->available))->toBeTrue()
        ->and($payout->estimate->amount->format())->toBe('$90.00');
});

it('counts only the fulfillments delivered since the last payout', function (): void {
    $seller = $this->seller();
    $before = $this->deliveredFulfillmentFor($seller, priceCents: 5_000, deliveredAt: $this->moment('2026-08-10 10:00:00'));
    app(RunWeeklyPayout::class)($this->moment('2026-08-17 09:00:00'));
    $this->deliveredFulfillmentFor($seller, priceCents: 7_000, deliveredAt: $this->moment('2026-08-19 10:00:00'));

    $payout = NextPayout::for($seller, $this->moment('2026-08-20 09:00:00'));

    expect($payout->estimate->releasedOrderCount)->toBe(1);
});

it('counts every delivered fulfillment when the seller has never been paid', function (): void {
    $seller = $this->seller();
    $this->deliveredFulfillmentFor($seller, priceCents: 5_000, deliveredAt: $this->moment('2026-08-10 10:00:00'));
    $this->deliveredFulfillmentFor($seller, priceCents: 7_000, deliveredAt: $this->moment('2026-08-19 10:00:00'));

    $payout = NextPayout::for($seller, $this->moment('2026-08-20 09:00:00'));

    expect($payout->estimate->releasedOrderCount)->toBe(2);
});

it('does not count a Sunday delivery again once the payout that settled it has run', function (): void {
    $seller = $this->seller();
    $this->deliveredFulfillmentFor(
        $seller,
        priceCents: 5_000,
        orderedAt: $this->moment('2026-08-10 09:00:00'),
        shippedAt: $this->moment('2026-08-11 09:00:00'),
        deliveredAt: $this->moment('2026-08-16 20:00:00'), // the settled period's last day
    );
    app(RunWeeklyPayout::class)($this->moment('2026-08-17 09:00:00'));

    $payout = NextPayout::for($seller, $this->moment('2026-08-19 09:00:00'));

    expect($payout->estimate->releasedOrderCount)->toBe(0)
        ->and($payout->estimate->amount->isZero())->toBeTrue();
});

it('pays out on the Monday of the week following the one in progress', function (): void {
    $seller = $this->seller();

    $payout = NextPayout::for($seller, $this->moment('2026-08-19 09:00:00'));

    expect($payout->estimate->payoutDate->format('Y-m-d'))->toBe('2026-08-24');
});
