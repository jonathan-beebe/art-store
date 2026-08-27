<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

it('refuses an unknown kind or malformed json body', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/description-sections", [
        'kind' => 'nonsense',
        'body_json' => 'not json',
    ]);

    $response->assertSessionHasErrors(['kind', 'body_json']);
});
