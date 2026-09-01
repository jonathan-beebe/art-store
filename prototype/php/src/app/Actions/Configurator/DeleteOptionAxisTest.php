<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Domain\Configurator\PricingMode;
use App\Domain\DomainRuleViolation;
use App\Models\OptionAxis;
use App\Models\OptionValue;
use App\Models\Variant;
use App\Models\VariantOption;

it('deletes an axis no variant references', function (): void {
    $axis = OptionAxis::factory()->create();
    OptionValue::factory()->create(['axis_id' => $axis->id]);

    app(DeleteOptionAxis::class)($axis);

    expect(OptionAxis::find($axis->id))->toBeNull();
});

it('refuses to delete an axis a variant still selects a value from', function (): void {
    $axis = OptionAxis::factory()->create();
    $value = OptionValue::factory()->create(['axis_id' => $axis->id]);
    $variant = Variant::factory()->create(['listing_id' => $axis->listing_id]);
    VariantOption::factory()->create(['variant_id' => $variant->id, 'axis_id' => $axis->id, 'option_value_id' => $value->id]);

    app(DeleteOptionAxis::class)($axis);
})->throws(DomainRuleViolation::class);

it('syncs the listing’s derived price, dropping the deleted axis’s contribution', function (): void {
    $listing = $this->listing($this->seller());
    $metal = app(CreateOptionAxis::class)($listing, 'Metal', pricingMode: PricingMode::Standalone);
    app(AddOptionValue::class)($metal, 'Gold', isDefault: true, priceCents: 1800);
    $size = app(CreateOptionAxis::class)($listing, 'Size', pricingMode: PricingMode::Standalone);
    app(AddOptionValue::class)($size, '8x10', isDefault: true, priceCents: 2400);
    expect($listing->refresh()->price_cents)->toBe(4200);

    app(DeleteOptionAxis::class)($metal);

    expect($listing->refresh()->price_cents)->toBe(2400);
});
