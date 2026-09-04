<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Models\ModifierOption;

it('deletes a modifier option', function (): void {
    $option = ModifierOption::factory()->create();

    app(DeleteModifierOption::class)($option);

    expect(ModifierOption::find($option->id))->toBeNull();
});
