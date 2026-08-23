<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Actions\Fulfillment\ConfirmDelivered;
use App\Actions\Fulfillment\MarkShipped;
use App\Actions\Orders\FinalizeOrder;
use App\Models\Customer;
use App\Models\Fulfillment;
use App\Models\Order;

$paidOrderFor = function (Customer $customer): Order {
    $listing = test()->listing(test()->seller('Blue Kiln Studio'), [
        'title' => 'Harbour at Dawn',
        'price_cents' => 24500,
    ]);
    $order = test()->orderFor($customer, $listing);

    return app(FinalizeOrder::class)($order, '4242424242424242', test()->moment('2026-08-20 10:00:00'));
};

$ship = function (Fulfillment $fulfillment): void {
    app(MarkShipped::class)($fulfillment, 'Royal Mail', 'RM123456789GB', test()->moment('2026-08-21 09:00:00'));
};

it('lists the orders of the visitor', function (): void {
    $shopper = $this->arriveAs($this->verifiedCustomer());
    $listing = $this->listing($this->seller(), ['title' => 'Harbour at Dawn', 'price_cents' => 24500]);
    $this->orderFor($shopper, $listing);
    $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller(), ['title' => 'Winter Elm']));

    $response = $this->get('/orders');

    $response->assertOk();
    $response->assertSee('Harbour at Dawn');
    $response->assertSee('$245.00');
    $response->assertDontSee('Winter Elm');
});

it('groups the items by seller with their fulfillment status', function () use ($paidOrderFor): void {
    $shopper = $this->arriveAs($this->verifiedCustomer());
    $order = $paidOrderFor($shopper);

    $response = $this->get(route('shop.order', $order));

    $response->assertOk();
    $response->assertSee('Blue Kiln Studio');
    $response->assertSee('Harbour at Dawn');
    $response->assertSee('Awaiting shipment');
});

it('shows the carrier and tracking once shipped', function () use ($paidOrderFor, $ship): void {
    $shopper = $this->arriveAs($this->verifiedCustomer());
    $order = $paidOrderFor($shopper);
    $ship($order->fulfillments()->sole());

    $response = $this->get(route('shop.order', $order));

    $response->assertSee('Royal Mail');
    $response->assertSee('RM123456789GB');
    $response->assertSee('Confirm delivery');
});

it('offers no delivery confirmation before shipping', function () use ($paidOrderFor): void {
    $shopper = $this->arriveAs($this->verifiedCustomer());
    $order = $paidOrderFor($shopper);

    $this->get(route('shop.order', $order))->assertDontSee('Confirm delivery');
});

it('offers no delivery confirmation once delivered', function () use ($paidOrderFor, $ship): void {
    $shopper = $this->arriveAs($this->verifiedCustomer());
    $order = $paidOrderFor($shopper);
    $ship($order->fulfillments()->sole());
    app(ConfirmDelivered::class)($order->fulfillments()->sole(), $this->moment('2026-08-22 09:00:00'));

    $this->get(route('shop.order', $order))->assertDontSee('Confirm delivery');
});

it('refuses another customer reading the order', function () use ($paidOrderFor): void {
    $order = $paidOrderFor($this->verifiedCustomer());
    $this->arriveAs($this->verifiedCustomer());

    $this->get(route('shop.order', $order))->assertNotFound();
});
