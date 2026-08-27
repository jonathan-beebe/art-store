<?php

declare(strict_types=1);

namespace App\Models;

it('names the modifier and the option value it gates on', function (): void {
    $modifier = Modifier::factory()->create();
    $value = OptionValue::factory()->create();
    $scope = ModifierScope::factory()->create(['modifier_id' => $modifier->id, 'option_value_id' => $value->id]);

    expect($scope->modifier()->first()?->id)->toBe($modifier->id)
        ->and($scope->optionValue()->first()?->id)->toBe($value->id);
});
