<?php

declare(strict_types=1);

namespace App\Orders;

use App\Models\OrderItem;

it('reads the distinct listings an order spans', function (): void {
    $seller = $this->seller();
    $listingA = $this->listing($seller, ['price_cents' => 24500]);
    $listingB = $this->listing($seller, ['price_cents' => 12000]);
    $order = $this->orderFor($this->verifiedCustomer(), $listingA, $listingB);

    expect(OrderListingIds::of($order))->toEqualCanonicalizing([$listingA->id, $listingB->id]);
});

it('lists a listing once even when the order holds two lines for it', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $order = $this->orderFor($this->verifiedCustomer(), $listing);
    OrderItem::factory()->create(['order_id' => $order->id, 'listing_id' => $listing->id]);

    expect(OrderListingIds::of($order))->toBe([$listing->id]);
});
