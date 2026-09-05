<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Models\OptionAxis;
use App\Models\OptionValue;
use App\Models\Variant;
use App\Models\VariantOption;

it('sets enabled on every variant selecting the given option value, leaving the others alone', function (bool $enabled): void {
    $listing = $this->listing($this->seller());
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    $large = OptionValue::factory()->create(['axis_id' => $axis->id]);
    $small = OptionValue::factory()->create(['axis_id' => $axis->id]);

    $matching = Variant::factory()->create(['listing_id' => $listing->id, 'enabled' => ! $enabled, 'combo_key' => $large->id]);
    VariantOption::factory()->create(['variant_id' => $matching->id, 'axis_id' => $axis->id, 'option_value_id' => $large->id]);

    $other = Variant::factory()->create(['listing_id' => $listing->id, 'enabled' => ! $enabled, 'combo_key' => $small->id]);
    VariantOption::factory()->create(['variant_id' => $other->id, 'axis_id' => $axis->id, 'option_value_id' => $small->id]);

    $count = app(SetVariantsEnabledByOptionValue::class)($listing, $large, $enabled);

    expect($count)->toBe(1)
        ->and($matching->fresh()?->enabled)->toBe($enabled)
        ->and($other->fresh()?->enabled)->toBe(! $enabled);
})->with([
    'enables' => [true],
    'disables' => [false],
]);
