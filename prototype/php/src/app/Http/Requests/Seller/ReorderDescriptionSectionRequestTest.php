<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Models\DescriptionSection;

it('refuses a direction other than up or down', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $section = DescriptionSection::factory()->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/description-sections/{$section->id}/reorder", [
        'direction' => 'sideways',
    ]);

    $response->assertSessionHasErrors('direction');
});
