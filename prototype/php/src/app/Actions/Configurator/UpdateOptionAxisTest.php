<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Models\OptionAxis;
use App\Models\Property;

it('renames an axis, reassigns its property, and repositions it', function (): void {
    $axis = OptionAxis::factory()->create(['name' => 'Metal', 'position' => 0]);
    $property = Property::factory()->create();

    $updated = app(UpdateOptionAxis::class)($axis, 'Finish', $property, 3);

    expect($updated->name)->toBe('Finish')
        ->and($updated->property_id)->toBe($property->id)
        ->and($updated->position)->toBe(3);
});

it('clears the property when the axis becomes custom', function (): void {
    $axis = OptionAxis::factory()->create(['property_id' => Property::factory()->create()->id]);

    $updated = app(UpdateOptionAxis::class)($axis, 'Custom label', null, 0);

    expect($updated->property_id)->toBeNull();
});
