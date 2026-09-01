<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Domain\Orders\FulfillmentStatus;
use App\Models\Refund;

it('declines a parcel and refunds the customer', function (): void {
    $seller = $this->seller();
    $fulfillment = $this->paidFulfillmentFor($seller, priceCents: 10000);

    $response = $this->actingAs($seller, 'seller')
        ->post("/seller/orders/{$fulfillment->id}/decline", ['reason' => 'The kiln cracked the glaze.']);

    $response->assertRedirect(route('seller.orders.show', $fulfillment->id));
    $response->assertSessionHas('status', 'Declined and refunded.');
    expect($fulfillment->refresh()->status)->toBe(FulfillmentStatus::Declined)
        ->and(Refund::sole()->amount_cents)->toBe(10000);
});

it('refuses a decline after the parcel shipped', function (): void {
    $seller = $this->seller();
    $fulfillment = $this->shippedFulfillmentFor($seller);

    $response = $this->actingAs($seller, 'seller')
        ->post("/seller/orders/{$fulfillment->id}/decline", ['reason' => 'Too late.']);

    $response->assertSessionHasErrors();
    expect($fulfillment->refresh()->status)->toBe(FulfillmentStatus::Shipped);
});
