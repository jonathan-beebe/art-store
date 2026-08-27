<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Models\OptionAxis;
use App\Models\OptionValue;
use App\Models\Variant;
use App\Models\VariantOption;

it('enables every variant selecting the given option value', function (): void {
    $listing = $this->listing($this->seller());
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    $large = OptionValue::factory()->create(['axis_id' => $axis->id]);
    $small = OptionValue::factory()->create(['axis_id' => $axis->id]);

    $matching = Variant::factory()->disabled()->create(['listing_id' => $listing->id, 'combo_key' => $large->id]);
    VariantOption::factory()->create(['variant_id' => $matching->id, 'axis_id' => $axis->id, 'option_value_id' => $large->id]);

    $other = Variant::factory()->disabled()->create(['listing_id' => $listing->id, 'combo_key' => $small->id]);
    VariantOption::factory()->create(['variant_id' => $other->id, 'axis_id' => $axis->id, 'option_value_id' => $small->id]);

    $count = app(SetVariantsEnabledByOptionValue::class)($listing, $large, true);

    expect($count)->toBe(1)
        ->and($matching->fresh()?->enabled)->toBeTrue()
        ->and($other->fresh()?->enabled)->toBeFalse();
});

it('disables every variant selecting the given option value', function (): void {
    $listing = $this->listing($this->seller());
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    $value = OptionValue::factory()->create(['axis_id' => $axis->id]);
    $variant = Variant::factory()->create(['listing_id' => $listing->id, 'enabled' => true]);
    VariantOption::factory()->create(['variant_id' => $variant->id, 'axis_id' => $axis->id, 'option_value_id' => $value->id]);

    app(SetVariantsEnabledByOptionValue::class)($listing, $value, false);

    expect($variant->fresh()?->enabled)->toBeFalse();
});
