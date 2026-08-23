<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Domain\Orders\FulfillmentStatus;
use App\Domain\Orders\OrderStatus;

it('lets the customer confirm delivery', function (): void {
    $shopper = $this->arriveAs($this->verifiedCustomer());
    $fulfillment = $this->shippedFulfillmentFor(
        $this->seller(),
        $shopper,
        priceCents: 24500,
        trackingNumber: 'RM123456789GB',
        shippedAt: $this->moment('2026-08-21 09:00:00'),
    );

    $response = $this->post(route('shop.order.delivered', [$fulfillment->order_id, $fulfillment->id]));

    $response->assertRedirect(route('shop.order', $fulfillment->order_id));
    expect($fulfillment->fresh()->status)->toBe(FulfillmentStatus::Delivered)
        ->and($fulfillment->order->fresh()->status)->toBe(OrderStatus::Delivered);
});

it('refuses to let another customer confirm delivery', function (): void {
    $fulfillment = $this->shippedFulfillmentFor(
        $this->seller(),
        $this->verifiedCustomer(),
        priceCents: 24500,
        trackingNumber: 'RM123456789GB',
        shippedAt: $this->moment('2026-08-21 09:00:00'),
    );
    $this->arriveAs($this->verifiedCustomer());

    $response = $this->post(route('shop.order.delivered', [$fulfillment->order_id, $fulfillment->id]));

    $response->assertNotFound();
    expect($fulfillment->fresh()->status)->toBe(FulfillmentStatus::Shipped);
});
