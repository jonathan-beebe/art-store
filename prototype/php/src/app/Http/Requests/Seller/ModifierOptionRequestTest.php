<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Models\Modifier;

it('refuses a malformed add-on price', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $modifier = Modifier::factory()->select()->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/modifiers/{$modifier->id}/options", [
        'label' => 'Serif',
        'add_on_price' => 'lots',
        'position' => 0,
    ]);

    $response->assertSessionHasErrors('add_on_price');
});
