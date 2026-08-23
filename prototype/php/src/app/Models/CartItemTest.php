<?php

declare(strict_types=1);

namespace App\Models;

it('converts itself into the cart line the listing it holds prices', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['price_cents' => 4500]);
    $cart = $this->cartFor($this->anonymousCustomer());
    $item = CartItem::create(['cart_id' => $cart->id, 'listing_id' => $listing->id, 'quantity' => 3]);

    $line = $item->toLine();

    expect($line->sellerId)->toBe($seller->id)
        ->and($line->unitPrice)->toBeMoney(4500)
        ->and($line->quantity)->toBe(3);
});
