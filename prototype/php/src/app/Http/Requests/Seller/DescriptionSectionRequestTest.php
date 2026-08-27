<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

it('refuses an unknown kind', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/description-sections", [
        'kind' => 'nonsense',
    ]);

    $response->assertSessionHasErrors(['kind']);
});
