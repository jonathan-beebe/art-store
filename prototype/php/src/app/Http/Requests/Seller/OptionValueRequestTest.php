<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Models\OptionAxis;

it('refuses a malformed surcharge', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/option-axes/{$axis->id}/option-values", [
        'label' => 'Gold',
        'surcharge' => 'a lot',
        'position' => 0,
    ]);

    $response->assertSessionHasErrors('surcharge');
});
