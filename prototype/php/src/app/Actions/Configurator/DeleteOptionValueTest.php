<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Domain\DomainRuleViolation;
use App\Models\OptionAxis;
use App\Models\OptionValue;
use App\Models\Variant;
use App\Models\VariantOption;

it('deletes a value no variant selects', function (): void {
    $value = OptionValue::factory()->create();

    app(DeleteOptionValue::class)($value);

    expect(OptionValue::find($value->id))->toBeNull();
});

it('refuses to delete a value a variant still selects', function (): void {
    $axis = OptionAxis::factory()->create();
    $value = OptionValue::factory()->create(['axis_id' => $axis->id]);
    $variant = Variant::factory()->create(['listing_id' => $axis->listing_id]);
    VariantOption::factory()->create(['variant_id' => $variant->id, 'axis_id' => $axis->id, 'option_value_id' => $value->id]);

    app(DeleteOptionValue::class)($value);
})->throws(DomainRuleViolation::class);
