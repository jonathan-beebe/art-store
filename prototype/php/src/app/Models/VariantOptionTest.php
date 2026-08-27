<?php

declare(strict_types=1);

namespace App\Models;

it('names the variant, axis, and option value it joins', function (): void {
    $variant = Variant::factory()->create();
    $axis = OptionAxis::factory()->create();
    $value = OptionValue::factory()->create(['axis_id' => $axis->id]);
    $link = VariantOption::factory()->create([
        'variant_id' => $variant->id,
        'axis_id' => $axis->id,
        'option_value_id' => $value->id,
    ]);

    expect($link->variant()->first()?->id)->toBe($variant->id)
        ->and($link->axis()->first()?->id)->toBe($axis->id)
        ->and($link->optionValue()->first()?->id)->toBe($value->id);
});
