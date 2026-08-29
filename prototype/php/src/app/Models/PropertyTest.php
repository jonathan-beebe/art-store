<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Configurator\PropertyDataType;

it('enumerates its values and lists the categories granting it', function (): void {
    $property = Property::factory()->create();
    PropertyValue::factory()->count(2)->create(['property_id' => $property->id]);
    CategoryProperty::factory()->create(['property_id' => $property->id]);

    expect($property->values()->count())->toBe(2)
        ->and($property->categoryProperties()->count())->toBe(1);
});

it('casts its data type', function (): void {
    expect(Property::factory()->create()->data_type)->toBe(PropertyDataType::Enum)
        ->and(Property::factory()->text()->create()->data_type)->toBe(PropertyDataType::Text)
        ->and(Property::factory()->number()->create()->data_type)->toBe(PropertyDataType::Number);
});
