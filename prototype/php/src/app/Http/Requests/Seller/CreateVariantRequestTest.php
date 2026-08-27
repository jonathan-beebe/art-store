<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

it('refuses a malformed price override', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/variants", [
        'quantity' => 1,
        'price_override' => 'lots',
    ]);

    $response->assertSessionHasErrors('price_override');
});
