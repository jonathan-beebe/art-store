<?php

declare(strict_types=1);

namespace App\Models;

it('applies product-wide with no scope rows', function (): void {
    $modifier = Modifier::factory()->create();

    expect($modifier->appliesTo(['ovl_anything']))->toBeTrue()
        ->and($modifier->appliesTo([]))->toBeTrue();
});

it('applies only when the selection includes a scoped value', function (): void {
    $modifier = Modifier::factory()->create();
    $scopedValue = OptionValue::factory()->create();
    ModifierScope::factory()->create(['modifier_id' => $modifier->id, 'option_value_id' => $scopedValue->id]);

    expect($modifier->appliesTo([$scopedValue->id]))->toBeTrue()
        ->and($modifier->appliesTo(['ovl_someone_else']))->toBeFalse();
});
