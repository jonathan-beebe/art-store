<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Domain\RateLimiting\RateLimitValue;
use App\Models\Category;
use App\Models\CategoryProperty;
use App\Models\OptionAxis;
use App\Models\OptionValue;
use App\Models\Property;
use App\Models\Variant;
use App\Models\VariantOption;
use Illuminate\Support\Facades\Config;

it('lists the listing’s axes with their options', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id, 'name' => 'Metal']);
    OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => 'Gold']);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/option-axes");

    $response->assertOk();
    $response->assertSee('Metal');
    $response->assertSee('Gold');
});

it('offers only the current categorys usable-as-axis properties in the picker', function (): void {
    $seller = $this->seller();
    $category = Category::factory()->create();
    $axisProperty = Property::factory()->create(['name' => 'Ring Size']);
    CategoryProperty::factory()->create(['category_id' => $category->id, 'property_id' => $axisProperty->id, 'usable_as_axis' => true, 'usable_as_attribute' => false]);
    $attributeOnlyProperty = Property::factory()->create(['name' => 'Material Only']);
    CategoryProperty::factory()->create(['category_id' => $category->id, 'property_id' => $attributeOnlyProperty->id, 'usable_as_axis' => false, 'usable_as_attribute' => true]);
    $elsewhereProperty = Property::factory()->create(['name' => 'Elsewhere Property']);
    $listing = $this->listing($seller, ['category_id' => $category->id]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/option-axes");

    $response->assertSee('Ring Size');
    $response->assertDontSee('Material Only');
    $response->assertDontSee('Elsewhere Property');
});

it('offers no catalog properties for an uncategorized listing', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    Property::factory()->create(['name' => 'Somewhere Property']);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/option-axes");

    $response->assertDontSee('Somewhere Property');
});

it('refuses another sellers listing axes page', function (): void {
    $listing = $this->listing($this->seller('Other Studio'));

    $response = $this->actingAs($this->seller(), 'seller')->get("/seller/listings/{$listing->id}/option-axes");

    $response->assertNotFound();
});

it('adds a custom axis', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/option-axes", [
        'name' => 'Engraving Placement',
        'position' => 0,
    ]);

    $response->assertRedirect(route('seller.listings.option-axes.index', $listing));
    $axis = OptionAxis::where('listing_id', $listing->id)->sole();
    expect($axis->name)->toBe('Engraving Placement')
        ->and($axis->property_id)->toBeNull();
});

it('adds an axis backed by a catalog property', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $property = Property::factory()->create();

    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/option-axes", [
        'name' => 'Metal',
        'property_id' => $property->id,
        'position' => 1,
    ]);

    expect(OptionAxis::where('listing_id', $listing->id)->sole()->property_id)->toBe($property->id);
});

it('refuses adding an axis to another sellers listing', function (): void {
    $listing = $this->listing($this->seller('Other Studio'));

    $response = $this->actingAs($this->seller(), 'seller')->post("/seller/listings/{$listing->id}/option-axes", [
        'name' => 'Metal',
        'position' => 0,
    ]);

    $response->assertNotFound();
    expect(OptionAxis::count())->toBe(0);
});

it('updates an axis', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id, 'name' => 'Metal']);

    $response = $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}/option-axes/{$axis->id}", [
        'name' => 'Finish',
        'position' => 2,
    ]);

    $response->assertRedirect(route('seller.listings.option-axes.index', $listing));
    expect($axis->fresh()?->name)->toBe('Finish')
        ->and($axis->fresh()?->position)->toBe(2);
});

it('answers not found updating an axis from another listing', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $otherListing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $otherListing->id]);

    $response = $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}/option-axes/{$axis->id}", [
        'name' => 'Finish',
        'position' => 0,
    ]);

    $response->assertNotFound();
});

it('removes an axis no variant references', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($seller, 'seller')->delete("/seller/listings/{$listing->id}/option-axes/{$axis->id}");

    $response->assertRedirect(route('seller.listings.option-axes.index', $listing));
    expect(OptionAxis::find($axis->id))->toBeNull();
});

it('refuses to remove an axis a variant still selects a value from', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    $value = OptionValue::factory()->create(['axis_id' => $axis->id]);
    $variant = Variant::factory()->create(['listing_id' => $listing->id]);
    VariantOption::factory()->create(['variant_id' => $variant->id, 'axis_id' => $axis->id, 'option_value_id' => $value->id]);

    $response = $this->actingAs($seller, 'seller')->delete("/seller/listings/{$listing->id}/option-axes/{$axis->id}");

    $response->assertSessionHasErrors();
    expect(OptionAxis::find($axis->id))->not->toBeNull();
});

it('refuses to remove another sellers axis', function (): void {
    $listing = $this->listing($this->seller('Other Studio'));
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($this->seller(), 'seller')->delete("/seller/listings/{$listing->id}/option-axes/{$axis->id}");

    $response->assertNotFound();
    expect(OptionAxis::find($axis->id))->not->toBeNull();
});

it('trips the listing-write limit adding an axis, re-rendering the index with nothing saved', function (): void {
    Config::set('rate_limits.listing_write', RateLimitValue::parse('1/1h', 'RATE_LIMIT_LISTING_WRITE'));
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/option-axes", ['name' => 'First', 'position' => 0]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/option-axes", ['name' => 'Second', 'position' => 1]);

    $response->assertStatus(429);
    $response->assertHeader('Retry-After');
    expect(OptionAxis::where('listing_id', $listing->id)->count())->toBe(1);
});

it('trips the listing-write limit updating an axis', function (): void {
    Config::set('rate_limits.listing_write', RateLimitValue::parse('1/1h', 'RATE_LIMIT_LISTING_WRITE'));
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id, 'name' => 'Old']);
    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/option-axes", ['name' => 'Consumes the budget', 'position' => 0]);

    $response = $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}/option-axes/{$axis->id}", ['name' => 'New', 'position' => 0]);

    $response->assertStatus(429);
    expect($axis->fresh()?->name)->toBe('Old');
});

it('trips the listing-write limit removing an axis', function (): void {
    Config::set('rate_limits.listing_write', RateLimitValue::parse('1/1h', 'RATE_LIMIT_LISTING_WRITE'));
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/option-axes", ['name' => 'Consumes the budget', 'position' => 0]);

    $response = $this->actingAs($seller, 'seller')->delete("/seller/listings/{$listing->id}/option-axes/{$axis->id}");

    $response->assertStatus(429);
    expect(OptionAxis::find($axis->id))->not->toBeNull();
});
