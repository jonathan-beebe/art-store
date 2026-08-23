<?php

declare(strict_types=1);

namespace App\Actions\Cart;

it('takes a listing back out of the cart', function (): void {
    $cart = $this->cartFor($this->verifiedCustomer());
    $seller = $this->seller();
    $kept = $this->listing($seller);
    $removed = $this->listing($seller);
    $addToCart = app(AddToCart::class);
    $now = $this->moment('2026-08-20 09:00:00');
    $addToCart($cart, $kept, 1, $now);
    $addToCart($cart, $removed, 1, $now);

    app(RemoveFromCart::class)($cart, $removed);

    expect($cart->items()->pluck('listing_id')->all())->toBe([$kept->id]);
});

it('changes nothing when removing a listing the cart never held', function (): void {
    $cart = $this->cartFor($this->verifiedCustomer());
    $listing = $this->listing($this->seller());

    app(RemoveFromCart::class)($cart, $listing);

    expect($cart->items()->count())->toBe(0);
});
