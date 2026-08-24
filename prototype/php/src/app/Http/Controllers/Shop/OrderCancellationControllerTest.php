<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Actions\Orders\FinalizeOrder;
use App\Domain\Listings\ListingStatus;
use App\Domain\Orders\OrderStatus;

it('cancels the visitors own unpaid order and puts the stock back', function (): void {
    $customer = $this->visitor();
    $listing = $this->listing($this->seller(), ['quantity' => 1]);
    $order = $this->orderFor($customer, $listing);

    $response = $this->post("/orders/{$order->id}/cancel");

    $response->assertRedirect(route('shop.order', $order));
    $response->assertSessionHas('status', 'Order cancelled.');
    expect($order->fresh()?->status)->toBe(OrderStatus::Cancelled)
        ->and($listing->refresh()->status)->toBe(ListingStatus::ForSale);
});

it('answers 404 for another customers order', function (): void {
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller()));
    $this->visitor();

    $response = $this->post("/orders/{$order->id}/cancel");

    $response->assertNotFound();
    expect($order->fresh()?->status)->toBe(OrderStatus::AwaitingPayment);
});

it('refuses to cancel an order that has been paid', function (): void {
    $customer = $this->visitor();
    $order = $this->orderFor($customer, $this->listing($this->seller()));
    app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));

    $response = $this->post("/orders/{$order->id}/cancel");

    $response->assertSessionHasErrors();
    expect($order->fresh()?->status)->toBe(OrderStatus::Paid);
});
