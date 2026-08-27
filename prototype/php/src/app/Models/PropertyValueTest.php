<?php

declare(strict_types=1);

namespace App\Models;

it('belongs to its property', function (): void {
    $property = Property::factory()->create();
    $value = PropertyValue::factory()->create(['property_id' => $property->id]);

    expect($value->property()->first()?->id)->toBe($property->id);
});
