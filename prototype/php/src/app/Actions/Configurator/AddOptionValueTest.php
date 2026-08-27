<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Models\OptionAxis;
use App\Models\PropertyValue;

it('adds a value to an axis, surcharge and default included', function (): void {
    $axis = OptionAxis::factory()->create();
    $catalogValue = PropertyValue::factory()->create();

    $value = app(AddOptionValue::class)($axis, 'Gold', 500, true, 2, $catalogValue);

    expect($value->axis_id)->toBe($axis->id)
        ->and($value->label)->toBe('Gold')
        ->and($value->surcharge_cents)->toBe(500)
        ->and($value->is_default)->toBeTrue()
        ->and($value->position)->toBe(2)
        ->and($value->property_value_id)->toBe($catalogValue->id);
});

it('defaults to no surcharge and no catalog value', function (): void {
    $value = app(AddOptionValue::class)(OptionAxis::factory()->create(), 'Silver');

    expect($value->surcharge_cents)->toBe(0)
        ->and($value->is_default)->toBeFalse()
        ->and($value->property_value_id)->toBeNull();
});
