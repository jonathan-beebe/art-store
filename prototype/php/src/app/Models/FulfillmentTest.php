<?php

declare(strict_types=1);

namespace App\Models;

use App\Actions\Fulfillment\DeclineFulfillment;
use App\Actions\Fulfillment\RefundFulfillment;
use App\Domain\Orders\FulfillmentStatus;

it('reads its subtotal, the platform fee, and the seller net as money', function (): void {
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller(), ['price_cents' => 45000]));
    $fulfillment = $order->fulfillments()->sole();

    expect($fulfillment->subtotal())->toBeMoney(45000)
        ->and($fulfillment->fee())->toBeMoney(4500)
        ->and($fulfillment->net())->toBeMoney(40500);
});

it('adds the fee and the net back up to the subtotal', function (): void {
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller(), ['price_cents' => 4599]));
    $fulfillment = $order->fulfillments()->sole();

    expect($fulfillment->fee()->add($fulfillment->net())->equals($fulfillment->subtotal()))->toBeTrue();
});

it('reads the ledger entries it produced', function (): void {
    $fulfillment = $this->deliveredFulfillmentFor($this->seller(), priceCents: 10000);

    expect($fulfillment->ledgerEntries()->count())->toBe(2);
});

it('counts every status the table holds, in one row each', function (): void {
    $this->paidFulfillmentFor($this->seller());
    $this->deliveredFulfillmentFor($this->seller());

    $counts = [];
    foreach (Fulfillment::query()->countedByStatus()->get() as $row) {
        $counts[$row->status->value] = $row->tally;
    }

    expect($counts)->toEqualCanonicalizing([
        FulfillmentStatus::AwaitingShipment->value => 1,
        FulfillmentStatus::Delivered->value => 1,
    ]);
});

it('counts every status across the whole platform', function (): void {
    $this->paidFulfillmentFor($this->seller());

    expect(Fulfillment::platformCountsByStatus())->toBe([FulfillmentStatus::AwaitingShipment->value => 1]);
});

it('earns the platform fee on a fulfillment still live and forgoes it on one refunded', function (): void {
    $admin = Admin::factory()->create();
    $this->deliveredFulfillmentFor($this->seller(), priceCents: 10000);
    $refunded = $this->deliveredFulfillmentFor($this->seller(), priceCents: 5000);
    app(RefundFulfillment::class)($refunded, $admin, 'Arrived damaged.', $this->moment('2026-08-23 09:00:00'));

    $fees = Fulfillment::platformFees();

    expect($fees->earned->cents)->toBe(1000)
        ->and($fees->refunded->cents)->toBe(500);
});

it('forgoes the fee on a fulfillment a seller declined', function (): void {
    $fulfillment = $this->paidFulfillmentFor($this->seller(), priceCents: 10000);
    app(DeclineFulfillment::class)($fulfillment, 'Out of stock.', $this->moment('2026-08-20 11:00:00'));

    expect(Fulfillment::platformFees()->refunded->cents)->toBe(1000);
});
