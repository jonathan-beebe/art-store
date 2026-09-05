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
    expect($fulfillment->refresh()->status)->toBe(FulfillmentStatus::Delivered)
        ->and($fulfillment->order->refresh()->status)->toBe(OrderStatus::Delivered);
});

it('refuses to confirm a delivery that was already confirmed', function (): void {
    $shopper = $this->arriveAs($this->verifiedCustomer());
    $fulfillment = $this->deliveredFulfillmentFor($this->seller(), $shopper, priceCents: 24500);
    $order = route('shop.order', $fulfillment->order_id);

    $response = $this->from($order)
        ->followingRedirects()
        ->post(route('shop.order.delivered', [$fulfillment->order_id, $fulfillment->id]));

    $response->assertOk();
    $response->assertSee('A fulfillment cannot move from delivered to delivered.');
    expect($fulfillment->refresh()->status)->toBe(FulfillmentStatus::Delivered);
});

it('refuses a fulfillment that belongs to another order', function (): void {
    $shopper = $this->arriveAs($this->verifiedCustomer());
    $mine = $this->shippedFulfillmentFor($this->seller(), $shopper);
    $theirs = $this->shippedFulfillmentFor($this->seller('Other Studio'), $shopper);

    $response = $this->post(route('shop.order.delivered', [$mine->order_id, $theirs->id]));

    $response->assertNotFound();
    expect($theirs->refresh()->status)->toBe(FulfillmentStatus::Shipped);
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
    expect($fulfillment->refresh()->status)->toBe(FulfillmentStatus::Shipped);
});
