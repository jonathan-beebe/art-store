<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Actions\Orders\FinalizeOrder;
use App\Domain\Orders\OrderStatus;
use App\Models\Customer;
use App\Models\Order;

$unpaidOrderFor = function (Customer $customer): Order {
    return test()->orderFor($customer, test()->listing(test()->seller(), ['price_cents' => 24500]));
};

it('asks a verified customer for a card', function () use ($unpaidOrderFor): void {
    $shopper = $this->arriveAs($this->verifiedCustomer());
    $order = $unpaidOrderFor($shopper);

    $response = $this->get(route('shop.order.pay', $order));

    $response->assertOk();
    $response->assertSee('name="card_number"', escape: false);
});

it('sends an unverified visitor to sign in first', function () use ($unpaidOrderFor): void {
    $visitor = $this->visitor();
    $order = $unpaidOrderFor($visitor);

    $response = $this->get(route('shop.order.pay', $order));

    $response->assertRedirect(route('auth.customer.login', [
        'redirect_to' => route('shop.order.pay', $order, absolute: false),
    ]));
});

it('sends an unverified visitor submitting a card to sign in first', function () use ($unpaidOrderFor): void {
    $visitor = $this->visitor();
    $order = $unpaidOrderFor($visitor);

    $response = $this->post(route('shop.order.pay', $order), ['card_number' => '4242 4242 4242 4242']);

    $response->assertRedirect(route('auth.customer.login', [
        'redirect_to' => route('shop.order.pay', $order, absolute: false),
    ]));
    expect($order->fresh()->status)->toBe(OrderStatus::PendingVerification);
});

it('refuses to let another customer read or pay the order', function (string $method) use ($unpaidOrderFor): void {
    $order = $unpaidOrderFor($this->verifiedCustomer());
    $this->arriveAs($this->verifiedCustomer());

    $this->call($method, route('shop.order.pay', $order), ['card_number' => '4242 4242 4242 4242'])
        ->assertNotFound();
})->with(['GET', 'POST']);

it('sends a paid order back to the order page', function () use ($unpaidOrderFor): void {
    $shopper = $this->arriveAs($this->verifiedCustomer());
    $order = $unpaidOrderFor($shopper);
    app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));

    $this->get(route('shop.order.pay', $order))->assertRedirect(route('shop.order', $order));
});

it('reports a declined card and pays on retry', function () use ($unpaidOrderFor): void {
    $shopper = $this->arriveAs($this->verifiedCustomer());
    $order = $unpaidOrderFor($shopper);

    $declined = $this->followingRedirects()
        ->post(route('shop.order.pay', $order), ['card_number' => '4000 0000 0000 0002']);

    $declined->assertSee('Your card was declined.');
    $declined->assertSee('name="card_number"', escape: false);
    expect($order->fresh()->status)->toBe(OrderStatus::PaymentFailed);

    $retried = $this->post(route('shop.order.pay', $order), ['card_number' => '4242 4242 4242 4242']);

    $retried->assertRedirect(route('shop.order', $order));
    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
});
