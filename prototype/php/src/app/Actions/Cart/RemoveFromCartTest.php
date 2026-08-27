<?php

declare(strict_types=1);

namespace App\Actions\Cart;

use App\Models\CartItem;

it('takes a line back out of the cart', function (): void {
    $cart = $this->cartFor($this->verifiedCustomer());
    $seller = $this->seller();
    $kept = $this->listing($seller);
    $removed = $this->listing($seller);
    $addToCart = app(AddToCart::class);
    $now = $this->moment('2026-08-20 09:00:00');
    $addToCart($cart, $kept, 1, $now);
    $addToCart($cart, $removed, 1, $now);
    $removedItem = $cart->items()->where('listing_id', $removed->id)->sole();

    app(RemoveFromCart::class)($removedItem);

    expect($cart->items()->pluck('listing_id')->all())->toBe([$kept->id]);
});

it('removes exactly the line asked for, leaving a second configuration of the same listing untouched', function (): void {
    $cart = $this->cartFor($this->verifiedCustomer());
    $listing = $this->listing($this->seller());
    $addToCart = app(AddToCart::class);
    $now = $this->moment('2026-08-20 09:00:00');
    $addToCart($cart, $listing, 1, $now);
    $first = $cart->items()->sole();
    // A second, distinct fingerprint for the same listing — the shape a
    // configured line takes once a buyer configures the same listing twice.
    $second = CartItem::factory()->create(['cart_id' => $cart->id, 'listing_id' => $listing->id, 'fingerprint' => 'a-second-configuration']);

    app(RemoveFromCart::class)($first);

    expect($cart->items()->pluck('id')->all())->toBe([$second->id]);
});
