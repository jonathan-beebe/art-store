<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CartItem;
use Illuminate\Auth\Access\Response;

it('lets a customer delete a line from their own cart', function (): void {
    $customer = $this->anonymousCustomer();
    $listing = $this->listing($this->seller());
    $item = CartItem::factory()->create(['cart_id' => $customer->cart()->id, 'listing_id' => $listing->id]);

    $response = (new CartItemPolicy)->delete($customer, $item);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->allowed())->toBeTrue();
});

it('answers not found for a line in another customers cart', function (): void {
    $owner = $this->anonymousCustomer();
    $listing = $this->listing($this->seller());
    $item = CartItem::factory()->create(['cart_id' => $owner->cart()->id, 'listing_id' => $listing->id]);

    $response = (new CartItemPolicy)->delete($this->anonymousCustomer(), $item);

    expect($response->denied())->toBeTrue()
        ->and($response->status())->toBe(404);
});
