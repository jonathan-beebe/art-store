<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Models\ModifierOption;

it('updates a modifier option’s label, add-on price, and position', function (): void {
    $option = ModifierOption::factory()->create(['label' => 'Serif', 'add_on_price_cents' => 0, 'position' => 0]);

    $updated = app(UpdateModifierOption::class)($option, 'Script', 300, 1);

    expect($updated->label)->toBe('Script')
        ->and($updated->add_on_price_cents)->toBe(300)
        ->and($updated->position)->toBe(1);
});
