<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

it('refuses a quantity break below 2, or a discount outside 1 to 9999 basis points', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/quantity-breaks", [
        'min_qty' => 1,
        'discount_bps' => 10000,
    ]);

    $response->assertSessionHasErrors(['min_qty', 'discount_bps']);
});
