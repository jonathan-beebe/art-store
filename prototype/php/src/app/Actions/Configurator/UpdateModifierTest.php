<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Domain\Configurator\ModifierKind;
use App\Models\Modifier;

it('updates every field a modifier can carry', function (): void {
    $modifier = Modifier::factory()->create(['kind' => ModifierKind::Text, 'prompt' => 'Old prompt']);

    $updated = app(UpdateModifier::class)(
        $modifier,
        ModifierKind::Measurement,
        'Engraved length',
        'Measured in millimeters.',
        true,
        2,
        500,
        20,
        'mm',
        10.0,
        100.0,
        50,
    );

    expect($updated->kind)->toBe(ModifierKind::Measurement)
        ->and($updated->prompt)->toBe('Engraved length')
        ->and($updated->instructions)->toBe('Measured in millimeters.')
        ->and($updated->required)->toBeTrue()
        ->and($updated->position)->toBe(2)
        ->and($updated->unit)->toBe('mm')
        ->and($updated->min_value)->toBe(10.0)
        ->and($updated->max_value)->toBe(100.0)
        ->and($updated->rate_cents_per_unit)->toBe(50)
        ->and($modifier->fresh()?->add_on_price_cents)->toBe(500)
        ->and($modifier->fresh()?->char_limit)->toBe(20);
});
