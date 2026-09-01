<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Domain\Configurator\UnitState;
use App\Models\Unit;
use App\Models\Variant;

it('refuses an unknown state', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $variant = Variant::factory()->serialized()->create(['listing_id' => $listing->id]);
    $unit = Unit::factory()->create(['variant_id' => $variant->id]);

    $response = $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}/variants/{$variant->id}/units/{$unit->id}", [
        'label' => $unit->label,
        'state' => 'discontinued',
    ]);

    $response->assertSessionHasErrors('state');
});

it('clears existing measurements when every row is left blank', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $variant = Variant::factory()->serialized()->create(['listing_id' => $listing->id]);
    $unit = Unit::factory()->create(['variant_id' => $variant->id, 'specs_json' => ['height_mm' => 10]]);

    $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}/variants/{$variant->id}/units/{$unit->id}", [
        'label' => $unit->label,
        'state' => UnitState::Available->value,
        'specs' => [
            ['label' => '', 'value' => ''],
        ],
    ]);

    expect($unit->fresh()?->specs_json)->toBeNull();
});
