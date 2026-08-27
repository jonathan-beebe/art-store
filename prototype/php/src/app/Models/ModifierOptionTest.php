<?php

declare(strict_types=1);

namespace App\Models;

it('belongs to its modifier and carries its add-on as money', function (): void {
    $modifier = Modifier::factory()->create();
    $option = ModifierOption::factory()->pricedAt(200)->create(['modifier_id' => $modifier->id]);

    expect($option->modifier()->first()?->id)->toBe($modifier->id)
        ->and($option->addOn()->cents)->toBe(200);
});
