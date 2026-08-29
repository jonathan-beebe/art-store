<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Domain\DomainRuleViolation;
use App\Models\OptionAxis;
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

it('updates a standalone option’s price and keeps its surcharge at zero', function (): void {
    $axis = OptionAxis::factory()->standalone()->create();
    $value = OptionValue::factory()->priced(1800)->create(['axis_id' => $axis->id]);

    $updated = app(UpdateOptionValue::class)($value, $value->label, 999, false, 0, null, 2400);

    expect($updated->price_cents)->toBe(2400)
        ->and($updated->surcharge_cents)->toBe(0);
});

it('refuses to blank out a standalone option’s price', function (): void {
    $axis = OptionAxis::factory()->standalone()->create();
    $value = OptionValue::factory()->priced(1800)->create(['axis_id' => $axis->id]);

    app(UpdateOptionValue::class)($value, $value->label, 0, false, 0, null, null);
})->throws(DomainRuleViolation::class, 'Every option on this choice needs its own price.');
