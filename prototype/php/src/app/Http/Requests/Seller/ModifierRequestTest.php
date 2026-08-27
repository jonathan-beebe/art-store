<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

it('refuses an unknown kind or a missing prompt', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/modifiers", [
        'kind' => 'nonsense',
        'prompt' => '',
        'position' => 0,
    ]);

    $response->assertSessionHasErrors(['kind', 'prompt']);
});
