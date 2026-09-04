<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Domain\Configurator\UnitState;
use App\Models\Unit;
use App\Models\Variant;

it('updates a unit’s label, state, condition note, specs, and price override', function (): void {
    $unit = Unit::factory()->create(['variant_id' => Variant::factory()->serialized()->create()->id, 'label' => '#1']);

    $updated = app(UpdateUnit::class)($unit, '#1 (repaired)', UnitState::Sold, 'Small chip repaired', ['height_mm' => 200], 5000);

    expect($updated->label)->toBe('#1 (repaired)')
        ->and($updated->state)->toBe(UnitState::Sold)
        ->and($updated->condition_note)->toBe('Small chip repaired')
        ->and($updated->specs_json)->toBe(['height_mm' => 200])
        ->and($updated->price_override_cents)->toBe(5000);
});

it('clears optional fields back to null', function (): void {
    $unit = Unit::factory()->create([
        'variant_id' => Variant::factory()->serialized()->create()->id,
        'condition_note' => 'Chipped',
        'specs_json' => ['a' => 1],
        'price_override_cents' => 100,
    ]);

    $updated = app(UpdateUnit::class)($unit, $unit->label, UnitState::Available, null, null, null);

    expect($updated->condition_note)->toBeNull()
        ->and($updated->specs_json)->toBeNull()
        ->and($updated->price_override_cents)->toBeNull();
});
