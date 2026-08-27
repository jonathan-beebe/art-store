<?php

declare(strict_types=1);

namespace App\Models;

it('belongs to its axis and an optional catalog value', function (): void {
    $axis = OptionAxis::factory()->create();
    $catalogValue = PropertyValue::factory()->create();
    $value = OptionValue::factory()->create(['axis_id' => $axis->id, 'property_value_id' => $catalogValue->id]);

    expect($value->axis()->first()?->id)->toBe($axis->id)
        ->and($value->propertyValue()->first()?->id)->toBe($catalogValue->id);
});

it('reads the variant options and modifier scopes naming it', function (): void {
    $value = OptionValue::factory()->create();
    VariantOption::factory()->create(['option_value_id' => $value->id]);
    ModifierScope::factory()->create(['option_value_id' => $value->id]);

    expect($value->variantOptions()->count())->toBe(1)
        ->and($value->modifierScopes()->count())->toBe(1);
});

it('carries its surcharge as money, and defaults to none', function (): void {
    expect(OptionValue::factory()->surcharging(850)->create()->surcharge()->cents)->toBe(850)
        ->and(OptionValue::factory()->create()->surcharge()->cents)->toBe(0)
        ->and(OptionValue::factory()->default()->create()->is_default)->toBeTrue();
});
