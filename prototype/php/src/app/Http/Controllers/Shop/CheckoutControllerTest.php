<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Domain\Orders\OrderStatus;
use App\Models\Customer;
use App\Models\Order;

$fillCart = function (): void {
    test()->listing(test()->seller(), ['slug' => 'harbour-at-dawn', 'price_cents' => 24500]);
    test()->post('/cart/harbour-at-dawn');
};

/**
 * @return array<string, string>
 */
$checkoutFields = function (): array {
    return [
        'email' => 'guest@example.com',
        'shipping_name' => 'Ada Lovelace',
        'shipping_line1' => '12 Analytical Way',
        'shipping_city' => 'London',
        'shipping_region' => 'Greater London',
        'shipping_postal_code' => 'EC1A 1BB',
        'shipping_country' => 'GB',
    ];
};

it('sends an empty cart back to the cart page', function (): void {
    $this->get('/checkout')->assertRedirect(route('shop.cart'));
});

it('prefills and locks the address of a signed in customer', function () use ($fillCart): void {
    $this->actingAs(Customer::factory()->create(['email' => 'shopper@example.com']), 'customer');
    $fillCart();

    $response = $this->get('/checkout');

    $response->assertOk();
    $response->assertSee('value="shopper@example.com" readonly', escape: false);
    $response->assertSee('name="card_number"', escape: false);
});

it('gives a guest no card fields before verifying', function () use ($fillCart): void {
    $this->visitor();
    $fillCart();

    $response = $this->get('/checkout');

    $response->assertOk();
    $response->assertDontSee('name="card_number"', escape: false);
});

it('places an order that waits for verification for a guest', function () use ($fillCart, $checkoutFields): void {
    $this->visitor();
    $fillCart();

    $response = $this->post('/checkout', $checkoutFields());

    $order = Order::sole();
    expect($order->status)->toBe(OrderStatus::PendingVerification)
        ->and($order->email)->toBe('guest@example.com')
        ->and($order->shipping_name)->toBe('Ada Lovelace');
    $response->assertRedirect(route('shop.order', $order));
    $response->assertSessionHas('debug_magic_link');
});

it('explains on the order page that a link was sent', function () use ($fillCart, $checkoutFields): void {
    $this->visitor();
    $fillCart();

    $response = $this->followingRedirects()->post('/checkout', $checkoutFields());

    $response->assertSee('Check your email');
    $response->assertSee('/auth/magic/', escape: false);
});

it('carries the guest order to their account and pays it on verification', function () use ($fillCart, $checkoutFields): void {
    $this->visitor();
    $shopper = Customer::factory()->create(['email' => 'guest@example.com']);
    $fillCart();
    $this->post('/checkout', $checkoutFields());
    $order = Order::sole();

    $this->get(session('debug_magic_link'))->assertRedirect(route('shop.order.pay', $order));
    expect($order->fresh()->customer_id)->toBe($shopper->id);

    $response = $this->post(route('shop.order.pay', $order), ['card_number' => '4242 4242 4242 4242']);

    $response->assertRedirect(route('shop.order', $order));
    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
});

it('lets a verified customer pay as they place the order', function () use ($fillCart, $checkoutFields): void {
    $this->actingAs(Customer::factory()->create(['email' => 'shopper@example.com']), 'customer');
    $fillCart();

    $response = $this->post('/checkout', $checkoutFields() + ['card_number' => '4242424242424242']);

    $order = Order::sole();
    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($order->email)->toBe('shopper@example.com');
    $response->assertRedirect(route('shop.order', $order));
});

it('requires a verified customer to give a card', function () use ($fillCart, $checkoutFields): void {
    $this->actingAs(Customer::factory()->create(), 'customer');
    $fillCart();

    $response = $this->post('/checkout', $checkoutFields());

    $response->assertSessionHasErrors('card_number');
    expect(Order::count())->toBe(0);
});

it('leaves the order unpaid with a reason when the card is declined', function () use ($fillCart, $checkoutFields): void {
    $this->actingAs(Customer::factory()->create(), 'customer');
    $fillCart();

    $response = $this->followingRedirects()
        ->post('/checkout', $checkoutFields() + ['card_number' => '4000000000000002']);

    expect(Order::sole()->status)->toBe(OrderStatus::PaymentFailed);
    $response->assertSee('Your card was declined.');
});
