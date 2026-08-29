<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Domain\RateLimiting\RateLimitValue;
use App\Models\Category;
use App\Models\CategoryProperty;
use App\Models\ListingAttribute;
use App\Models\Property;
use App\Models\PropertyValue;
use Illuminate\Support\Facades\Config;

it('sets a value for a property the listings category grants as an attribute', function (): void {
    $seller = $this->seller();
    $category = Category::factory()->create();
    $property = Property::factory()->create(['name' => 'Material']);
    $value = PropertyValue::factory()->create(['property_id' => $property->id, 'label' => 'Walnut']);
    CategoryProperty::factory()->create(['category_id' => $category->id, 'property_id' => $property->id, 'usable_as_attribute' => true]);
    $listing = $this->listing($seller, ['category_id' => $category->id]);

    $response = $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}/attributes", [
        'attribute' => [$property->id => [$value->id]],
    ]);

    $response->assertRedirect(route('seller.listings.basics.edit', $listing));
    expect(ListingAttribute::where('listing_id', $listing->id)->sole()->property_value_id)->toBe($value->id);
});

it('ignores a property the listings category does not grant', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $property = Property::factory()->create();
    $value = PropertyValue::factory()->create(['property_id' => $property->id]);

    $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}/attributes", [
        'attribute' => [$property->id => [$value->id]],
    ]);

    expect(ListingAttribute::count())->toBe(0);
});

it('refuses to set attributes on another sellers listing', function (): void {
    $listing = $this->listing($this->seller('Other Studio'));

    $response = $this->actingAs($this->seller(), 'seller')->put("/seller/listings/{$listing->id}/attributes", [
        'attribute' => [],
    ]);

    $response->assertNotFound();
});

it('trips the listing-write limit setting attributes, re-rendering the edit screen with nothing saved', function (): void {
    Config::set('rate_limits.listing_write', RateLimitValue::parse('1/1h', 'RATE_LIMIT_LISTING_WRITE'));
    $seller = $this->seller();
    $category = Category::factory()->create();
    $property = Property::factory()->create();
    $value = PropertyValue::factory()->create(['property_id' => $property->id]);
    CategoryProperty::factory()->create(['category_id' => $category->id, 'property_id' => $property->id, 'usable_as_attribute' => true]);
    $listing = $this->listing($seller, ['category_id' => $category->id]);
    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/option-axes", ['name' => 'Consumes the budget', 'position' => 0]);

    $response = $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}/attributes", [
        'attribute' => [$property->id => [$value->id]],
    ]);

    $response->assertStatus(429);
    expect(ListingAttribute::count())->toBe(0);
});
