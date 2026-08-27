<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Models\OptionValue;
use App\Models\PropertyValue;

it('updates a value’s label, surcharge, default flag, position, and catalog value', function (): void {
    $value = OptionValue::factory()->create(['label' => 'Gold', 'surcharge_cents' => 500]);
    $catalogValue = PropertyValue::factory()->create();

    $updated = app(UpdateOptionValue::class)($value, 'Rose Gold', 750, true, 2, $catalogValue);

    expect($updated->label)->toBe('Rose Gold')
        ->and($updated->surcharge_cents)->toBe(750)
        ->and($updated->is_default)->toBeTrue()
        ->and($updated->position)->toBe(2)
        ->and($updated->property_value_id)->toBe($catalogValue->id);
});

it('clears the catalog value when set back to null', function (): void {
    $value = OptionValue::factory()->create(['property_value_id' => PropertyValue::factory()->create()->id]);

    $updated = app(UpdateOptionValue::class)($value, 'Silver', 0, false, 0, null);

    expect($updated->property_value_id)->toBeNull();
});
