<?php

declare(strict_types=1);

namespace App\Admin;

use App\Actions\Fulfillment\DeclineFulfillment;
use App\Actions\Fulfillment\RefundFulfillment;
use App\Domain\Orders\FulfillmentStatus;
use App\Models\Admin;

it('counts every status across the whole platform', function (): void {
    $this->paidFulfillmentFor($this->seller());

    expect(PlatformFulfillmentReader::countsByStatus())->toBe([FulfillmentStatus::AwaitingShipment->value => 1]);
});

it('earns the platform fee on a fulfillment still live and forgoes it on one refunded', function (): void {
    $admin = Admin::factory()->create();
    $this->deliveredFulfillmentFor($this->seller(), priceCents: 10000);
    $refunded = $this->deliveredFulfillmentFor($this->seller(), priceCents: 5000);
    app(RefundFulfillment::class)($refunded, $admin, 'Arrived damaged.', $this->moment('2026-08-23 09:00:00'));

    $fees = PlatformFulfillmentReader::fees();

    expect($fees->earned->cents)->toBe(1000)
        ->and($fees->refunded->cents)->toBe(500);
});

it('forgoes the fee on a fulfillment a seller declined', function (): void {
    $fulfillment = $this->paidFulfillmentFor($this->seller(), priceCents: 10000);
    app(DeclineFulfillment::class)($fulfillment, 'Out of stock.', $this->moment('2026-08-20 11:00:00'));

    expect(PlatformFulfillmentReader::fees()->refunded->cents)->toBe(1000);
});
