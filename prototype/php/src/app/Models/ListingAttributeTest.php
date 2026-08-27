<?php

declare(strict_types=1);

namespace App\Models;

it('names a listing’s property and value', function (): void {
    $listing = $this->listing($this->seller());
    $property = Property::factory()->create();
    $value = PropertyValue::factory()->create(['property_id' => $property->id]);
    $attribute = ListingAttribute::factory()->create([
        'listing_id' => $listing->id,
        'property_id' => $property->id,
        'property_value_id' => $value->id,
    ]);

    expect($attribute->listing()->first()?->id)->toBe($listing->id)
        ->and($attribute->property()->first()?->id)->toBe($property->id)
        ->and($attribute->propertyValue()->first()?->id)->toBe($value->id);
});
