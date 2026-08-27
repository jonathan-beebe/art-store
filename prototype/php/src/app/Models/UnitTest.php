<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Configurator\UnitState;

it('belongs to its variant and casts its state and specs', function (): void {
    $variant = Variant::factory()->create();
    $unit = Unit::factory()->create([
        'variant_id' => $variant->id,
        'specs_json' => ['height_mm' => 240, 'condition' => 'excellent'],
    ]);

    expect($unit->variant()->first()?->id)->toBe($variant->id)
        ->and($unit->state)->toBe(UnitState::Available)
        ->and($unit->specs_json)->toBe(['height_mm' => 240, 'condition' => 'excellent']);
});

it('moves through reserved and sold', function (): void {
    expect(Unit::factory()->reserved()->create()->state)->toBe(UnitState::Reserved)
        ->and(Unit::factory()->sold()->create()->state)->toBe(UnitState::Sold);
});
