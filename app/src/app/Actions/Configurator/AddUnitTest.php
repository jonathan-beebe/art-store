<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Models\Variant;

it('adds a serialized unit with its condition, specs, and price override', function (): void {
    $variant = Variant::factory()->serialized()->create();

    $unit = app(AddUnit::class)($variant, '#12', 'Small chip at base', ['height_mm' => 240], 4500);

    expect($unit->variant_id)->toBe($variant->id)
        ->and($unit->label)->toBe('#12')
        ->and($unit->condition_note)->toBe('Small chip at base')
        ->and($unit->specs_json)->toBe(['height_mm' => 240])
        ->and($unit->price_override_cents)->toBe(4500);
});

it('defaults to no condition note, specs, or price override', function (): void {
    $unit = app(AddUnit::class)(Variant::factory()->serialized()->create(), '#1');

    expect($unit->condition_note)->toBeNull()
        ->and($unit->specs_json)->toBeNull()
        ->and($unit->price_override_cents)->toBeNull();
});
