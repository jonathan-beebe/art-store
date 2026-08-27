<?php

declare(strict_types=1);

namespace App\Support\Configurator;

use App\Models\Listing;
use App\Models\ListingAttribute;
use App\Models\Property;
use App\Models\PropertyValue;

it('renders nothing for a listing with no attributes', function (): void {
    $listing = Listing::factory()->create();

    expect(ListingHighlights::forStorefront($listing))->toBe([]);
});

it('groups a listings attributes by property name with value labels', function (): void {
    $material = Property::factory()->create(['name' => 'Material']);
    $walnut = PropertyValue::factory()->create(['property_id' => $material->id, 'label' => 'Walnut']);
    $listing = Listing::factory()->create();
    ListingAttribute::factory()->create(['listing_id' => $listing->id, 'property_id' => $material->id, 'property_value_id' => $walnut->id]);

    expect(ListingHighlights::forStorefront($listing))->toBe([
        ['name' => 'Material', 'values' => ['Walnut']],
    ]);
});

it('keeps every value of a multivalued property under one heading', function (): void {
    $material = Property::factory()->create(['name' => 'Material']);
    $walnut = PropertyValue::factory()->create(['property_id' => $material->id, 'label' => 'Walnut']);
    $oak = PropertyValue::factory()->create(['property_id' => $material->id, 'label' => 'Oak']);
    $listing = Listing::factory()->create();
    ListingAttribute::factory()->create(['listing_id' => $listing->id, 'property_id' => $material->id, 'property_value_id' => $walnut->id]);
    ListingAttribute::factory()->create(['listing_id' => $listing->id, 'property_id' => $material->id, 'property_value_id' => $oak->id]);

    expect(ListingHighlights::forStorefront($listing))->toBe([
        ['name' => 'Material', 'values' => ['Walnut', 'Oak']],
    ]);
});

it('lists more than one property in the order the attributes were set', function (): void {
    $material = Property::factory()->create(['name' => 'Material']);
    $color = Property::factory()->create(['name' => 'Color']);
    $walnut = PropertyValue::factory()->create(['property_id' => $material->id, 'label' => 'Walnut']);
    $black = PropertyValue::factory()->create(['property_id' => $color->id, 'label' => 'Black']);
    $listing = Listing::factory()->create();
    ListingAttribute::factory()->create(['listing_id' => $listing->id, 'property_id' => $material->id, 'property_value_id' => $walnut->id]);
    ListingAttribute::factory()->create(['listing_id' => $listing->id, 'property_id' => $color->id, 'property_value_id' => $black->id]);

    expect(ListingHighlights::forStorefront($listing))->toBe([
        ['name' => 'Material', 'values' => ['Walnut']],
        ['name' => 'Color', 'values' => ['Black']],
    ]);
});
