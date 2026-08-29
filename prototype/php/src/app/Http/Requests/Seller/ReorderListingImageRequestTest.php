<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

it('refuses a direction other than up or down', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $image = $this->listingImage($listing);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/images/{$image->id}/reorder", [
        'direction' => 'sideways',
    ]);

    $response->assertSessionHasErrors('direction');
});
