<?php

declare(strict_types=1);

namespace App\Support\Configurator;

use App\Domain\Money\Money;
use App\Models\OptionAxis;
use App\Models\OptionValue;
use App\Models\Variant;
use App\Models\VariantOption;

it('sums the base price and every option surcharge, ignoring a price override', function (): void {
    $variant = Variant::factory()->overriddenAt(9999)->create();
    $axis = OptionAxis::factory()->create(['listing_id' => $variant->listing_id]);
    $value = OptionValue::factory()->surcharging(600)->create(['axis_id' => $axis->id]);
    VariantOption::factory()->create(['variant_id' => $variant->id, 'axis_id' => $axis->id, 'option_value_id' => $value->id]);
    $variant->load('options.optionValue');

    expect(VariantBuyerPrice::withoutOverride(Money::fromCents(2000), $variant)->cents)->toBe(2600);
});

it('returns the base price alone for a combination with no options', function (): void {
    $variant = Variant::factory()->create();
    $variant->load('options.optionValue');

    expect(VariantBuyerPrice::withoutOverride(Money::fromCents(1500), $variant)->cents)->toBe(1500);
});
