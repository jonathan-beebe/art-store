<?php

declare(strict_types=1);

namespace App\Actions\Cart;

use App\Models\Cart;
use App\Models\CartItem;

it('gives a customer without a cart one', function (): void {
    $customer = $this->anonymousCustomer();

    $cart = app(CurrentCart::class)($customer);

    expect($cart->customer_id)->toBe($customer->id)
        ->and(Cart::count())->toBe(1);
});

it('returns the same cart twice', function (): void {
    $customer = $this->anonymousCustomer();

    $first = app(CurrentCart::class)($customer);
    $second = app(CurrentCart::class)($customer);

    expect($second->id)->toBe($first->id);
});

it('picks the cart holding the items after a merge', function (): void {
    $customer = $this->verifiedCustomer();
    $this->cartFor($customer);
    $filled = $this->cartFor($customer);
    CartItem::create([
        'cart_id' => $filled->id,
        'listing_id' => $this->listing($this->seller())->id,
        'quantity' => 1,
    ]);

    expect(app(CurrentCart::class)($customer)->id)->toBe($filled->id);
});
