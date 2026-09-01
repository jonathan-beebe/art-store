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

it('syncs the listing’s derived price after deleting the standalone default option, falling back to the next one', function (): void {
    $listing = $this->listing($this->seller());
    $axis = OptionAxis::factory()->standalone()->create(['listing_id' => $listing->id]);
    $default = app(AddOptionValue::class)($axis, '8x10', isDefault: true, position: 0, priceCents: 1800);
    app(AddOptionValue::class)($axis, '11x14', position: 1, priceCents: 2400);
    expect($listing->refresh()->price_cents)->toBe(1800);

    app(DeleteOptionValue::class)($default);

    expect($listing->refresh()->price_cents)->toBe(2400);
});
