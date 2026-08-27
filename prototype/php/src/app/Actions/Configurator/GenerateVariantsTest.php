<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Domain\Configurator\ComboKey;
use App\Models\OptionAxis;
use App\Models\OptionValue;

it('generates the full cross product of the listing’s axes', function (): void {
    $listing = $this->listing($this->seller());
    $color = OptionAxis::factory()->create(['listing_id' => $listing->id, 'position' => 0]);
    $red = OptionValue::factory()->create(['axis_id' => $color->id]);
    $blue = OptionValue::factory()->create(['axis_id' => $color->id]);
    $size = OptionAxis::factory()->create(['listing_id' => $listing->id, 'position' => 1]);
    $small = OptionValue::factory()->create(['axis_id' => $size->id]);
    $large = OptionValue::factory()->create(['axis_id' => $size->id]);

    $variants = app(GenerateVariants::class)($listing);

    expect($variants)->toHaveCount(4);

    $comboKeys = array_map(fn ($v) => $v->combo_key, $variants);
    expect($comboKeys)->toEqualCanonicalizing([
        ComboKey::of([$red->id, $small->id])->value,
        ComboKey::of([$red->id, $large->id])->value,
        ComboKey::of([$blue->id, $small->id])->value,
        ComboKey::of([$blue->id, $large->id])->value,
    ]);
});

it('produces nothing for a listing with no axes', function (): void {
    expect(app(GenerateVariants::class)($this->listing($this->seller())))->toBe([]);
});

it('leaves an already-generated combination alone on a second run', function (): void {
    $listing = $this->listing($this->seller());
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    OptionValue::factory()->count(2)->create(['axis_id' => $axis->id]);

    $first = app(GenerateVariants::class)($listing);
    $second = app(GenerateVariants::class)($listing);

    expect($first)->toHaveCount(2)
        ->and($second)->toBe([])
        ->and($listing->variants()->count())->toBe(2);
});
