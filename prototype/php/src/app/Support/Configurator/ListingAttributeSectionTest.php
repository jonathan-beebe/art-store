<?php

declare(strict_types=1);

namespace App\Support\Configurator;

use App\Models\Category;
use App\Models\CategoryProperty;
use App\Models\Listing;
use App\Models\ListingAttribute;
use App\Models\Property;
use App\Models\PropertyValue;

it('offers no grants for an uncategorized listing', function (): void {
    $listing = Listing::factory()->create(['category_id' => null]);

    expect(ListingAttributeSection::grants($listing))->toBeEmpty();
});

it('offers the current categorys usable-as-attribute grants with their values', function (): void {
    $category = Category::factory()->create();
    $property = Property::factory()->create(['name' => 'Material']);
    PropertyValue::factory()->create(['property_id' => $property->id, 'label' => 'Walnut']);
    CategoryProperty::factory()->create(['category_id' => $category->id, 'property_id' => $property->id, 'usable_as_attribute' => true]);
    $listing = Listing::factory()->create(['category_id' => $category->id]);

    $grants = ListingAttributeSection::grants($listing);

    expect($grants)->toHaveCount(1)
        ->and($grants->first()?->property->name)->toBe('Material')
        ->and($grants->first()?->property->values->pluck('label')->all())->toBe(['Walnut']);
});

it('excludes a grant that is an axis rather than an attribute', function (): void {
    $category = Category::factory()->create();
    $property = Property::factory()->create();
    CategoryProperty::factory()->create(['category_id' => $category->id, 'property_id' => $property->id, 'usable_as_attribute' => false, 'usable_as_axis' => true]);
    $listing = Listing::factory()->create(['category_id' => $category->id]);

    expect(ListingAttributeSection::grants($listing))->toBeEmpty();
});

it('groups a listings existing values by property', function (): void {
    $property = Property::factory()->create();
    $first = PropertyValue::factory()->create(['property_id' => $property->id]);
    $second = PropertyValue::factory()->create(['property_id' => $property->id]);
    $listing = Listing::factory()->create();
    ListingAttribute::factory()->create(['listing_id' => $listing->id, 'property_id' => $property->id, 'property_value_id' => $first->id]);
    ListingAttribute::factory()->create(['listing_id' => $listing->id, 'property_id' => $property->id, 'property_value_id' => $second->id]);

    expect(ListingAttributeSection::selections($listing))->toBe([
        $property->id => [$first->id, $second->id],
    ]);
});

it('returns no selections for a listing with no attributes', function (): void {
    $listing = Listing::factory()->create();

    expect(ListingAttributeSection::selections($listing))->toBe([]);
});
