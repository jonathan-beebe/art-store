<?php

declare(strict_types=1);

namespace App\Models;

it('reads the customer and the listing it links', function (): void {
    $customer = $this->anonymousCustomer();
    $listing = $this->listing($this->seller());
    $favorite = Favorite::create(['customer_id' => $customer->id, 'listing_id' => $listing->id]);

    expect($favorite->customer->is($customer))->toBeTrue()
        ->and($favorite->listing->is($listing))->toBeTrue();
});
