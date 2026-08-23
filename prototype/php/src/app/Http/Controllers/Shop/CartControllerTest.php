<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Domain\Listings\ListingEventType;
use App\Domain\Listings\ListingStatus;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\ListingEvent;

it('adds a listing to the cart and records the event', function (): void {
    $visitor = $this->visitor();
    $listing = $this->listing($this->seller(), ['slug' => 'harbour-at-dawn']);

    $response = $this->post('/cart/harbour-at-dawn');

    $response->assertRedirect(route('shop.cart'));
    $item = CartItem::sole();
    expect($item->listing_id)->toBe($listing->id)
        ->and($item->quantity)->toBe(1)
        ->and($item->cart->customer_id)->toBe($visitor->id)
        ->and(ListingEvent::sole()->type)->toBe(ListingEventType::CartAdd);
});

it('shows the lines and the subtotal', function (): void {
    $this->visitor();
    $seller = $this->seller();
    $this->listing($seller, ['slug' => 'harbour-at-dawn', 'title' => 'Harbour at Dawn', 'price_cents' => 24500]);
    $this->listing($seller, ['slug' => 'winter-elm', 'title' => 'Winter Elm', 'price_cents' => 5500]);
    $this->post('/cart/harbour-at-dawn');
    $this->post('/cart/winter-elm');

    $response = $this->get('/cart');

    $response->assertOk();
    $response->assertSee('Harbour at Dawn');
    $response->assertSee('Winter Elm');
    $response->assertSee('$300.00');
});

it('removes a line', function (): void {
    $this->visitor();
    $this->listing($this->seller(), ['slug' => 'harbour-at-dawn']);
    $this->post('/cart/harbour-at-dawn');

    $response = $this->delete('/cart/harbour-at-dawn');

    $response->assertRedirect(route('shop.cart'));
    expect(CartItem::count())->toBe(0);
});

it('refuses a listing that is not for sale', function (): void {
    $this->visitor();
    $this->listing($this->seller(), [
        'slug' => 'sold-vase',
        'status' => ListingStatus::Sold,
        'quantity' => 0,
    ]);

    $response = $this->post('/cart/sold-vase');

    $response->assertRedirect();
    $response->assertSessionHas('error', 'That listing is no longer for sale.');
    expect(CartItem::count())->toBe(0);
});

it('says an empty cart is empty', function (): void {
    $response = $this->get('/cart');

    $response->assertOk();
    $response->assertSee('Your cart is empty');
});

it('survives the merge when the cart was filled before signing in', function (): void {
    $this->visitor();
    Customer::factory()->create(['email' => 'shopper@example.com']);
    $this->listing($this->seller(), ['slug' => 'harbour-at-dawn', 'title' => 'Harbour at Dawn']);
    $this->post('/cart/harbour-at-dawn');

    $this->post('/login', ['email' => 'shopper@example.com']);
    $this->get(session('debug_magic_link'));

    $response = $this->get('/cart');

    $response->assertSee('Harbour at Dawn');
    expect(CartItem::count())->toBe(1);
});
