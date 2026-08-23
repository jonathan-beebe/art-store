<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Domain\Orders\FulfillmentStatus;
use App\Domain\Orders\OrderStatus;

it('is partially shipped when a paid order has one of two fulfillments shipped', function (): void {
    $order = $this->paidOrderWithTwoSellers();
    $order->fulfillments()->orderBy('id')->first()->update(['status' => FulfillmentStatus::Shipped]);

    $order = app(RollUpOrderStatus::class)($order->load('fulfillments'));

    expect($order->status)->toBe(OrderStatus::PartiallyShipped);
});

it('is shipped when every fulfillment has shipped', function (): void {
    $order = $this->paidOrderWithTwoSellers();
    $order->fulfillments()->update(['status' => FulfillmentStatus::Shipped]);

    $order = app(RollUpOrderStatus::class)($order->load('fulfillments'));

    expect($order->status)->toBe(OrderStatus::Shipped);
});

it('is delivered when every fulfillment has been delivered', function (): void {
    $order = $this->paidOrderWithTwoSellers();
    $order->fulfillments()->update(['status' => FulfillmentStatus::Shipped]);
    app(RollUpOrderStatus::class)($order->load('fulfillments'));
    $order->fulfillments()->update(['status' => FulfillmentStatus::Delivered]);

    $order = app(RollUpOrderStatus::class)($order->load('fulfillments'));

    expect($order->status)->toBe(OrderStatus::Delivered);
});
