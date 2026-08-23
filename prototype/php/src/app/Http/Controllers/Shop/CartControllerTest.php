<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Domain\Listings\ListingEventType;
use App\Domain\Listings\ListingStatus;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\ListingEvent;
use Tests\StorefrontTestCase;

final class CartControllerTest extends StorefrontTestCase
{
    public function test_it_adds_a_listing_to_the_cart_and_records_the_event(): void
    {
        $visitor = $this->visitor();
        $listing = $this->listing($this->seller(), ['slug' => 'harbour-at-dawn']);

        $response = $this->post('/cart/harbour-at-dawn');

        $response->assertRedirect(route('shop.cart'));
        $item = CartItem::sole();
        $this->assertSame($listing->id, $item->listing_id);
        $this->assertSame(1, $item->quantity);
        $this->assertSame($visitor->id, $item->cart->customer_id);
        $this->assertSame(ListingEventType::CartAdd, ListingEvent::sole()->type);
    }

    public function test_it_shows_the_lines_and_the_subtotal(): void
    {
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
    }

    public function test_it_removes_a_line(): void
    {
        $this->visitor();
        $this->listing($this->seller(), ['slug' => 'harbour-at-dawn']);
        $this->post('/cart/harbour-at-dawn');

        $response = $this->delete('/cart/harbour-at-dawn');

        $response->assertRedirect(route('shop.cart'));
        $this->assertSame(0, CartItem::count());
    }

    public function test_it_refuses_a_listing_that_is_not_for_sale(): void
    {
        $this->visitor();
        $this->listing($this->seller(), [
            'slug' => 'sold-vase',
            'status' => ListingStatus::Sold,
            'quantity' => 0,
        ]);

        $response = $this->post('/cart/sold-vase');

        $response->assertRedirect();
        $response->assertSessionHas('error', 'That listing is no longer for sale.');
        $this->assertSame(0, CartItem::count());
    }

    public function test_an_empty_cart_says_so(): void
    {
        $response = $this->get('/cart');

        $response->assertOk();
        $response->assertSee('Your cart is empty');
    }

    public function test_a_cart_filled_before_signing_in_survives_the_merge(): void
    {
        $this->visitor();
        Customer::factory()->create(['email' => 'shopper@example.com']);
        $this->listing($this->seller(), ['slug' => 'harbour-at-dawn', 'title' => 'Harbour at Dawn']);
        $this->post('/cart/harbour-at-dawn');

        $this->post('/login', ['email' => 'shopper@example.com']);
        $this->get(session('debug_magic_link'));

        $response = $this->get('/cart');

        $response->assertSee('Harbour at Dawn');
        $this->assertSame(1, CartItem::count());
    }
}
