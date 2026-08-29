<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Models\Category;
use App\Models\CategoryProperty;
use App\Models\Listing;
use App\Models\ListingAttribute;
use App\Models\Property;
use App\Models\PropertyValue;

it('sets a value for a property the listings category grants as an attribute', function (): void {
    $category = Category::factory()->create();
    $property = Property::factory()->create();
    $value = PropertyValue::factory()->create(['property_id' => $property->id]);
    CategoryProperty::factory()->create(['category_id' => $category->id, 'property_id' => $property->id, 'usable_as_attribute' => true]);
    $listing = Listing::factory()->create(['category_id' => $category->id]);

    $attributes = app(SetListingAttributes::class)($listing, [$property->id => [$value->id]]);

    expect($attributes)->toHaveCount(1)
        ->and($listing->listingAttributes()->sole()->property_value_id)->toBe($value->id);
});

it('ignores a property the listings category does not grant', function (): void {
    $category = Category::factory()->create();
    $property = Property::factory()->create();
    $value = PropertyValue::factory()->create(['property_id' => $property->id]);
    $listing = Listing::factory()->create(['category_id' => $category->id]);

    app(SetListingAttributes::class)($listing, [$property->id => [$value->id]]);

    expect($listing->listingAttributes()->count())->toBe(0);
});

it('ignores every selection for an uncategorized listing', function (): void {
    $property = Property::factory()->create();
    $value = PropertyValue::factory()->create(['property_id' => $property->id]);
    $listing = Listing::factory()->create(['category_id' => null]);

    $attributes = app(SetListingAttributes::class)($listing, [$property->id => [$value->id]]);

    expect($attributes)->toBe([])
        ->and($listing->listingAttributes()->count())->toBe(0);
});

it('ignores a value id that does not belong to the property', function (): void {
    $category = Category::factory()->create();
    $property = Property::factory()->create();
    $otherProperty = Property::factory()->create();
    $strayValue = PropertyValue::factory()->create(['property_id' => $otherProperty->id]);
    CategoryProperty::factory()->create(['category_id' => $category->id, 'property_id' => $property->id, 'usable_as_attribute' => true]);
    $listing = Listing::factory()->create(['category_id' => $category->id]);

    app(SetListingAttributes::class)($listing, [$property->id => [$strayValue->id]]);

    expect($listing->listingAttributes()->count())->toBe(0);
});

it('keeps only one value for a property that is not multivalued', function (): void {
    $category = Category::factory()->create();
    $property = Property::factory()->create();
    $first = PropertyValue::factory()->create(['property_id' => $property->id]);
    $second = PropertyValue::factory()->create(['property_id' => $property->id]);
    CategoryProperty::factory()->create(['category_id' => $category->id, 'property_id' => $property->id, 'usable_as_attribute' => true, 'multivalued' => false]);
    $listing = Listing::factory()->create(['category_id' => $category->id]);

    app(SetListingAttributes::class)($listing, [$property->id => [$first->id, $second->id]]);

    expect($listing->listingAttributes()->count())->toBe(1)
        ->and($listing->listingAttributes()->sole()->property_value_id)->toBe($first->id);
});

it('keeps every checked value for a multivalued property', function (): void {
    $category = Category::factory()->create();
    $property = Property::factory()->create();
    $first = PropertyValue::factory()->create(['property_id' => $property->id]);
    $second = PropertyValue::factory()->create(['property_id' => $property->id]);
    CategoryProperty::factory()->create(['category_id' => $category->id, 'property_id' => $property->id, 'usable_as_attribute' => true, 'multivalued' => true]);
    $listing = Listing::factory()->create(['category_id' => $category->id]);

    app(SetListingAttributes::class)($listing, [$property->id => [$first->id, $second->id]]);

    expect($listing->listingAttributes()->pluck('property_value_id')->all())->toEqualCanonicalizing([$first->id, $second->id]);
});

it('replaces a previous selection rather than adding to it', function (): void {
    $category = Category::factory()->create();
    $property = Property::factory()->create();
    $first = PropertyValue::factory()->create(['property_id' => $property->id]);
    $second = PropertyValue::factory()->create(['property_id' => $property->id]);
    CategoryProperty::factory()->create(['category_id' => $category->id, 'property_id' => $property->id, 'usable_as_attribute' => true]);
    $listing = Listing::factory()->create(['category_id' => $category->id]);
    app(SetListingAttributes::class)($listing, [$property->id => [$first->id]]);

    app(SetListingAttributes::class)($listing, [$property->id => [$second->id]]);

    expect($listing->listingAttributes()->pluck('property_value_id')->all())->toBe([$second->id]);
});

it('clears a propertys values when nothing is checked for it', function (): void {
    $category = Category::factory()->create();
    $property = Property::factory()->create();
    $value = PropertyValue::factory()->create(['property_id' => $property->id]);
    CategoryProperty::factory()->create(['category_id' => $category->id, 'property_id' => $property->id, 'usable_as_attribute' => true]);
    $listing = Listing::factory()->create(['category_id' => $category->id]);
    app(SetListingAttributes::class)($listing, [$property->id => [$value->id]]);

    app(SetListingAttributes::class)($listing, []);

    expect($listing->listingAttributes()->count())->toBe(0);
});

it('is idempotent for a value already set', function (): void {
    $category = Category::factory()->create();
    $property = Property::factory()->create();
    $value = PropertyValue::factory()->create(['property_id' => $property->id]);
    CategoryProperty::factory()->create(['category_id' => $category->id, 'property_id' => $property->id, 'usable_as_attribute' => true]);
    $listing = Listing::factory()->create(['category_id' => $category->id]);

    app(SetListingAttributes::class)($listing, [$property->id => [$value->id]]);
    app(SetListingAttributes::class)($listing, [$property->id => [$value->id]]);

    expect(ListingAttribute::count())->toBe(1);
});
