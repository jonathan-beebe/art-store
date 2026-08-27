<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

it('refuses a missing name or an unknown catalog property', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/option-axes", [
        'name' => '',
        'property_id' => 'prp_does_not_exist',
        'position' => 0,
    ]);

    $response->assertSessionHasErrors(['name', 'property_id']);
});
