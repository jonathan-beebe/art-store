<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Models\Unit;
use App\Models\Variant;

it('refuses malformed specs json', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $variant = Variant::factory()->serialized()->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/variants/{$variant->id}/units", [
        'label' => '#1',
        'specs' => 'not json',
    ]);

    $response->assertSessionHasErrors('specs');
});

it('treats valid json that is not an object as no specs', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $variant = Variant::factory()->serialized()->create(['listing_id' => $listing->id]);

    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/variants/{$variant->id}/units", [
        'label' => '#1',
        'specs' => '"just a string"',
    ]);

    expect(Unit::where('variant_id', $variant->id)->sole()->specs_json)->toBeNull();
});
