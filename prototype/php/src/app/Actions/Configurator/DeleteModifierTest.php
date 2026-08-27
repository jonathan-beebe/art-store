<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Models\Modifier;
use App\Models\ModifierOption;

it('deletes a modifier and its options along with it', function (): void {
    $modifier = Modifier::factory()->create();
    $option = ModifierOption::factory()->create(['modifier_id' => $modifier->id]);

    app(DeleteModifier::class)($modifier);

    expect(Modifier::find($modifier->id))->toBeNull()
        ->and(ModifierOption::find($option->id))->toBeNull();
});
