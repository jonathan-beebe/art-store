<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Domain\RateLimiting\RateLimitValue;
use App\Models\OptionAxis;
use App\Models\OptionValue;
use App\Models\Variant;
use App\Models\VariantOption;
use Illuminate\Support\Facades\Config;

it('lists the listing’s sparse variants with a derived price', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['price_cents' => 2000]);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id, 'name' => 'Size']);
    $large = OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => 'Large', 'surcharge_cents' => 500]);
    $variant = Variant::factory()->create(['listing_id' => $listing->id, 'combo_key' => $large->id]);
    VariantOption::factory()->create(['variant_id' => $variant->id, 'axis_id' => $axis->id, 'option_value_id' => $large->id]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/variants");

    $response->assertOk();
    $response->assertSee('Large');
    $response->assertSee('$25.00', escape: false);
});

it('offers the add-variant form while a combination remains', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    OptionValue::factory()->create(['axis_id' => $axis->id]);
    OptionValue::factory()->create(['axis_id' => $axis->id]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/variants");

    $response->assertOk();
    $response->assertSee('Add variant');
    $response->assertDontSee('Every combination exists');
});

it('replaces the add-variant form with a note once every combination has a row', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    $only = OptionValue::factory()->create(['axis_id' => $axis->id]);
    $variant = Variant::factory()->create(['listing_id' => $listing->id, 'combo_key' => $only->id]);
    VariantOption::factory()->create(['variant_id' => $variant->id, 'axis_id' => $axis->id, 'option_value_id' => $only->id]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/variants");

    $response->assertOk();
    $response->assertSee('Every combination exists — edit rows above.');
    $response->assertDontSee('Add variant');
});

it('refuses another sellers listing variants page', function (): void {
    $listing = $this->listing($this->seller('Other Studio'));

    $response = $this->actingAs($this->seller(), 'seller')->get("/seller/listings/{$listing->id}/variants");

    $response->assertNotFound();
});

it('adds a sparse variant selecting one option per axis', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    $value = OptionValue::factory()->create(['axis_id' => $axis->id]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/variants", [
        'option_value_id' => [$axis->id => $value->id],
        'sku' => 'SKU-1',
        'price_override' => '19.99',
        'quantity' => 3,
    ]);

    $response->assertRedirect(route('seller.listings.variants.index', $listing));
    $variant = Variant::where('listing_id', $listing->id)->sole();
    expect($variant->sku)->toBe('SKU-1')
        ->and($variant->price_override_cents)->toBe(1999)
        ->and($variant->quantity)->toBe(3)
        ->and($variant->enabled)->toBeTrue();
});

it('adds the legacy single variant for an axis-free listing', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/variants", ['quantity' => 1]);

    expect(Variant::where('listing_id', $listing->id)->sole()->combo_key)->toBe('');
});

it('refuses an option value from the wrong axis', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    $otherAxis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    $wrongValue = OptionValue::factory()->create(['axis_id' => $otherAxis->id]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/variants", [
        'option_value_id' => [$axis->id => $wrongValue->id],
    ]);

    $response->assertSessionHasErrors("option_value_id.{$axis->id}");
    expect(Variant::count())->toBe(0);
});

it('updates a variant’s override, sku, quantity, serialization, and enablement', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $variant = Variant::factory()->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}/variants/{$variant->id}", [
        'sku' => 'NEW-SKU',
        'price_override' => '9.00',
        'quantity' => 5,
        'enabled' => '1',
    ]);

    $response->assertRedirect(route('seller.listings.variants.index', $listing));
    $updated = $variant->fresh();
    expect($updated?->sku)->toBe('NEW-SKU')
        ->and($updated?->price_override_cents)->toBe(900)
        ->and($updated?->quantity)->toBe(5)
        ->and($updated?->enabled)->toBeTrue();
});

it('disables a variant when the enabled box is unchecked', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $variant = Variant::factory()->create(['listing_id' => $listing->id, 'enabled' => true]);

    $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}/variants/{$variant->id}", ['quantity' => 1]);

    expect($variant->fresh()?->enabled)->toBeFalse();
});

it('answers not found updating a variant from another listing', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $otherListing = $this->listing($seller);
    $variant = Variant::factory()->create(['listing_id' => $otherListing->id]);

    $response = $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}/variants/{$variant->id}", ['quantity' => 1]);

    $response->assertNotFound();
});

it('trips the listing-write limit adding a variant', function (): void {
    Config::set('rate_limits.listing_write', RateLimitValue::parse('1/1h', 'RATE_LIMIT_LISTING_WRITE'));
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/variants", ['quantity' => 1]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/variants", ['quantity' => 1, 'sku' => 'Second']);

    $response->assertStatus(429);
    expect(Variant::where('listing_id', $listing->id)->count())->toBe(1);
});

it('trips the listing-write limit updating a variant', function (): void {
    Config::set('rate_limits.listing_write', RateLimitValue::parse('1/1h', 'RATE_LIMIT_LISTING_WRITE'));
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $variant = Variant::factory()->create(['listing_id' => $listing->id, 'sku' => 'Old']);
    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/variants", ['quantity' => 1]);

    $response = $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}/variants/{$variant->id}", ['sku' => 'New', 'quantity' => 1]);

    $response->assertStatus(429);
    expect($variant->fresh()?->sku)->toBe('Old');
});
