<?php

namespace App\Actions\Cart;

use App\Domain\Listings\ListingEventType;
use App\Models\ListingEvent;
use DomainException;
use Tests\CommerceTestCase;

final class AddToCartTest extends CommerceTestCase
{
    public function test_it_puts_a_listing_in_the_cart(): void
    {
        $customer = $this->verifiedCustomer();
        $cart = $this->cartFor($customer);
        $listing = $this->listing($this->seller(), ['quantity' => 3]);

        $item = app(AddToCart::class)($cart, $listing, 2, $this->moment('2026-08-20 09:00:00'));

        $this->assertSame($listing->id, $item->listing_id);
        $this->assertSame(2, $item->quantity);
    }

    public function test_adding_the_same_listing_twice_raises_the_quantity_on_one_line(): void
    {
        $cart = $this->cartFor($this->verifiedCustomer());
        $listing = $this->listing($this->seller(), ['quantity' => 5]);
        $addToCart = app(AddToCart::class);
        $now = $this->moment('2026-08-20 09:00:00');

        $addToCart($cart, $listing, 1, $now);
        $item = $addToCart($cart, $listing, 2, $now);

        $this->assertSame(3, $item->quantity);
        $this->assertSame(1, $cart->items()->count());
    }

    public function test_it_caps_the_quantity_at_the_stock_the_listing_has_left(): void
    {
        $cart = $this->cartFor($this->verifiedCustomer());
        $listing = $this->listing($this->seller(), ['quantity' => 2]);

        $item = app(AddToCart::class)($cart, $listing, 9, $this->moment('2026-08-20 09:00:00'));

        $this->assertSame(2, $item->quantity);
    }

    public function test_it_refuses_a_sold_out_listing(): void
    {
        $cart = $this->cartFor($this->verifiedCustomer());
        $listing = $this->listing($this->seller(), ['quantity' => 0]);

        $this->expectException(DomainException::class);

        app(AddToCart::class)($cart, $listing, 1, $this->moment('2026-08-20 09:00:00'));
    }

    public function test_it_records_the_add_as_a_listing_event(): void
    {
        $customer = $this->verifiedCustomer();
        $cart = $this->cartFor($customer);
        $listing = $this->listing($this->seller());

        app(AddToCart::class)($cart, $listing, 1, $this->moment('2026-08-20 09:00:00'));

        $event = ListingEvent::query()->sole();
        $this->assertSame(ListingEventType::CartAdd, $event->type);
        $this->assertSame($customer->id, $event->customer_id);
    }
}
