<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\QueryException;

it('rejects a second attribute for the same listing, property, and value', function (): void {
    $listing = $this->listing($this->seller());
    $property = Property::factory()->create();
    $value = PropertyValue::factory()->create(['property_id' => $property->id]);
    ListingAttribute::factory()->create([
        'listing_id' => $listing->id,
        'property_id' => $property->id,
        'property_value_id' => $value->id,
    ]);

    expect(fn () => ListingAttribute::factory()->create([
        'listing_id' => $listing->id,
        'property_id' => $property->id,
        'property_value_id' => $value->id,
    ]))->toThrow(QueryException::class);
});
