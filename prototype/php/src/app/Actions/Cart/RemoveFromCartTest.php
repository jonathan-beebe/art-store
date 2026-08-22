<?php

namespace App\Actions\Cart;

use Tests\CommerceTestCase;

final class RemoveFromCartTest extends CommerceTestCase
{
    public function test_it_takes_a_listing_back_out_of_the_cart(): void
    {
        $cart = $this->cartFor($this->verifiedCustomer());
        $seller = $this->seller();
        $kept = $this->listing($seller);
        $removed = $this->listing($seller);
        $addToCart = app(AddToCart::class);
        $now = $this->moment('2026-08-20 09:00:00');
        $addToCart($cart, $kept, 1, $now);
        $addToCart($cart, $removed, 1, $now);

        app(RemoveFromCart::class)($cart, $removed);

        $this->assertSame([$kept->id], $cart->items()->pluck('listing_id')->all());
    }

    public function test_removing_a_listing_the_cart_never_held_changes_nothing(): void
    {
        $cart = $this->cartFor($this->verifiedCustomer());
        $listing = $this->listing($this->seller());

        app(RemoveFromCart::class)($cart, $listing);

        $this->assertSame(0, $cart->items()->count());
    }
}
