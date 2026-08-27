<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Models\Unit;
use App\Models\Variant;

it('drops blank measurement rows and keeps the rest', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $variant = Variant::factory()->serialized()->create(['listing_id' => $listing->id]);

    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/variants/{$variant->id}/units", [
        'label' => '#1',
        'specs' => [
            ['label' => 'Height', 'value' => '26 cm'],
            ['label' => '', 'value' => ''],
            ['label' => 'Weight', 'value' => ''],
        ],
    ]);

    expect(Unit::where('variant_id', $variant->id)->sole()->specs_json)->toBe(['Height' => '26 cm']);
});

it('ignores a malformed measurement row that is not a label/value pair', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $variant = Variant::factory()->serialized()->create(['listing_id' => $listing->id]);

    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/variants/{$variant->id}/units", [
        'label' => '#1',
        'specs' => ['not a row', ['label' => 'Height', 'value' => '1 cm']],
    ]);

    expect(Unit::where('variant_id', $variant->id)->sole()->specs_json)->toBe(['Height' => '1 cm']);
});

it('treats an all-blank set of measurement rows as no measurements', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $variant = Variant::factory()->serialized()->create(['listing_id' => $listing->id]);

    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/variants/{$variant->id}/units", [
        'label' => '#1',
        'specs' => [
            ['label' => '', 'value' => ''],
            ['label' => '', 'value' => ''],
            ['label' => '', 'value' => ''],
        ],
    ]);

    expect(Unit::where('variant_id', $variant->id)->sole()->specs_json)->toBeNull();
});
