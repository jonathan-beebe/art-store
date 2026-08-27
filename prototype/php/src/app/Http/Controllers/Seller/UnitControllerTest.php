<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Domain\Configurator\UnitState;
use App\Domain\RateLimiting\RateLimitValue;
use App\Models\Unit;
use App\Models\Variant;
use Illuminate\Support\Facades\Config;

it('lists a variant’s units and its derived available count', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $variant = Variant::factory()->serialized()->create(['listing_id' => $listing->id]);
    Unit::factory()->create(['variant_id' => $variant->id, 'label' => '#1']);
    Unit::factory()->sold()->create(['variant_id' => $variant->id, 'label' => '#2']);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/variants/{$variant->id}/units");

    $response->assertOk();
    $response->assertSee('#1');
    $response->assertSee('1'); // the derived available count
});

it('refuses another sellers variant units page', function (): void {
    $listing = $this->listing($this->seller('Other Studio'));
    $variant = Variant::factory()->serialized()->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($this->seller(), 'seller')->get("/seller/listings/{$listing->id}/variants/{$variant->id}/units");

    $response->assertNotFound();
});

it('adds a unit with its condition, specs, and price override', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $variant = Variant::factory()->serialized()->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/variants/{$variant->id}/units", [
        'label' => '#12',
        'condition_note' => 'Small chip at base',
        'specs' => '{"height_mm": 240}',
        'price_override' => '45.00',
    ]);

    $response->assertRedirect(route('seller.listings.variants.units.index', [$listing, $variant]));
    $unit = Unit::where('variant_id', $variant->id)->sole();
    expect($unit->label)->toBe('#12')
        ->and($unit->condition_note)->toBe('Small chip at base')
        ->and($unit->specs_json)->toBe(['height_mm' => 240])
        ->and($unit->price_override_cents)->toBe(4500);
});

it('refuses a duplicate label on the same variant', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $variant = Variant::factory()->serialized()->create(['listing_id' => $listing->id]);
    Unit::factory()->create(['variant_id' => $variant->id, 'label' => '#1']);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/variants/{$variant->id}/units", [
        'label' => '#1',
    ]);

    $response->assertSessionHasErrors('label');
});

it('updates a unit’s label, state, specs, and price override', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $variant = Variant::factory()->serialized()->create(['listing_id' => $listing->id]);
    $unit = Unit::factory()->create(['variant_id' => $variant->id, 'label' => '#1']);

    $response = $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}/variants/{$variant->id}/units/{$unit->id}", [
        'label' => '#1',
        'state' => UnitState::Sold->value,
        'specs' => '{"height_mm": 240}',
        'price_override' => '50.00',
    ]);

    $response->assertRedirect(route('seller.listings.variants.units.index', [$listing, $variant]));
    $updated = $unit->fresh();
    expect($updated?->state)->toBe(UnitState::Sold)
        ->and($updated?->specs_json)->toBe(['height_mm' => 240])
        ->and($updated?->price_override_cents)->toBe(5000);
});

it('updates a unit with no specs field, clearing any it had', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $variant = Variant::factory()->serialized()->create(['listing_id' => $listing->id]);
    $unit = Unit::factory()->create(['variant_id' => $variant->id, 'specs_json' => ['height_mm' => 10]]);

    $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}/variants/{$variant->id}/units/{$unit->id}", [
        'label' => $unit->label,
        'state' => UnitState::Available->value,
    ]);

    expect($unit->fresh()?->specs_json)->toBeNull();
});

it('answers not found updating a unit from another variant', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $variant = Variant::factory()->serialized()->create(['listing_id' => $listing->id, 'combo_key' => 'a']);
    $otherVariant = Variant::factory()->serialized()->create(['listing_id' => $listing->id, 'combo_key' => 'b']);
    $unit = Unit::factory()->create(['variant_id' => $otherVariant->id]);

    $response = $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}/variants/{$variant->id}/units/{$unit->id}", [
        'label' => $unit->label,
        'state' => UnitState::Available->value,
    ]);

    $response->assertNotFound();
});

it('trips the listing-write limit adding a unit', function (): void {
    Config::set('rate_limits.listing_write', RateLimitValue::parse('1/1h', 'RATE_LIMIT_LISTING_WRITE'));
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $variant = Variant::factory()->serialized()->create(['listing_id' => $listing->id]);
    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/variants/{$variant->id}/units", ['label' => '#1']);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/variants/{$variant->id}/units", ['label' => '#2']);

    $response->assertStatus(429);
    expect(Unit::where('variant_id', $variant->id)->count())->toBe(1);
});

it('trips the listing-write limit updating a unit', function (): void {
    Config::set('rate_limits.listing_write', RateLimitValue::parse('1/1h', 'RATE_LIMIT_LISTING_WRITE'));
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $variant = Variant::factory()->serialized()->create(['listing_id' => $listing->id]);
    $unit = Unit::factory()->create(['variant_id' => $variant->id, 'label' => '#1']);
    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/variants/{$variant->id}/units", ['label' => '#2']);

    $response = $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}/variants/{$variant->id}/units/{$unit->id}", [
        'label' => '#1', 'state' => UnitState::Sold->value,
    ]);

    $response->assertStatus(429);
    expect($unit->fresh()?->state)->toBe(UnitState::Available);
});
