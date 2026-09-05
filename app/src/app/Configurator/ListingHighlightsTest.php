<?php

declare(strict_types=1);

namespace App\Configurator;

use App\Models\Listing;
use App\Models\ListingAttribute;
use App\Models\Property;
use App\Models\PropertyValue;
use Illuminate\Support\Facades\DB;

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

it('leaves Medium out, the page’s own Medium line already carries it', function (): void {
    $medium = Property::factory()->create(['name' => 'Medium']);
    $ceramic = PropertyValue::factory()->create(['property_id' => $medium->id, 'label' => 'Ceramic']);
    $listing = Listing::factory()->create();
    ListingAttribute::factory()->create(['listing_id' => $listing->id, 'property_id' => $medium->id, 'property_value_id' => $ceramic->id]);

    expect(ListingHighlights::forStorefront($listing))->toBe([]);
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

it('reads an eager-loaded relation without firing a query of its own', function (): void {
    $material = Property::factory()->create(['name' => 'Material']);
    $walnut = PropertyValue::factory()->create(['property_id' => $material->id, 'label' => 'Walnut']);
    $listing = Listing::factory()->create();
    ListingAttribute::factory()->create(['listing_id' => $listing->id, 'property_id' => $material->id, 'property_value_id' => $walnut->id]);
    $listing->load(['listingAttributes.property', 'listingAttributes.propertyValue']);

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $highlights = ListingHighlights::forStorefront($listing);

    expect($highlights)->toBe([['name' => 'Material', 'values' => ['Walnut']]])
        ->and($queries)->toBe(0);
});
