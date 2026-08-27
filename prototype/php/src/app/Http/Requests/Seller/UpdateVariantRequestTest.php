<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Models\Variant;

it('refuses a malformed price override', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $variant = Variant::factory()->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}/variants/{$variant->id}", [
        'price_override' => 'lots',
    ]);

    $response->assertSessionHasErrors('price_override');
});
