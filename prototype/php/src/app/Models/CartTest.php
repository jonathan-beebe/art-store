<?php

declare(strict_types=1);

namespace App\Models;

it('reads its items as cart lines', function (): void {
    $seller = $this->seller();
    $customer = $this->anonymousCustomer();
    $cart = $this->cartFor($customer);
    $listing = $this->listing($seller, ['price_cents' => 4500]);
    CartItem::create(['cart_id' => $cart->id, 'listing_id' => $listing->id, 'quantity' => 2]);

    $lines = $cart->lines();

    expect($lines)->toHaveCount(1)
        ->and($lines[0]->sellerId)->toBe($seller->id)
        ->and($lines[0]->unitPrice)->toBeMoney(4500)
        ->and($lines[0]->quantity)->toBe(2);
});

it('reads no lines for an empty cart', function (): void {
    $cart = $this->cartFor($this->anonymousCustomer());

    expect($cart->lines())->toBe([]);
});

it('reads the customer it belongs to', function (): void {
    $customer = $this->anonymousCustomer();
    $cart = $this->cartFor($customer);

    expect($cart->customer()->sole()->is($customer))->toBeTrue();
});
