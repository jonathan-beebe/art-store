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

it('crosses three axes of mixed size, including a single-value axis, in axis-position order', function (): void {
    $listing = $this->listing($this->seller());
    $metal = OptionAxis::factory()->create(['listing_id' => $listing->id, 'position' => 0]);
    $gold = OptionValue::factory()->create(['axis_id' => $metal->id, 'position' => 0]);
    $silver = OptionValue::factory()->create(['axis_id' => $metal->id, 'position' => 1]);
    $finish = OptionAxis::factory()->create(['listing_id' => $listing->id, 'position' => 1]);
    $polished = OptionValue::factory()->create(['axis_id' => $finish->id, 'position' => 0]);
    $size = OptionAxis::factory()->create(['listing_id' => $listing->id, 'position' => 2]);
    $small = OptionValue::factory()->create(['axis_id' => $size->id, 'position' => 0]);
    $medium = OptionValue::factory()->create(['axis_id' => $size->id, 'position' => 1]);
    $large = OptionValue::factory()->create(['axis_id' => $size->id, 'position' => 2]);

    $variants = app(GenerateVariants::class)($listing);

    expect($variants)->toHaveCount(6);

    $comboKeys = array_map(fn ($v) => $v->combo_key, $variants);
    expect($comboKeys)->toBe([
        ComboKey::of([$gold->id, $polished->id, $small->id])->value,
        ComboKey::of([$gold->id, $polished->id, $medium->id])->value,
        ComboKey::of([$gold->id, $polished->id, $large->id])->value,
        ComboKey::of([$silver->id, $polished->id, $small->id])->value,
        ComboKey::of([$silver->id, $polished->id, $medium->id])->value,
        ComboKey::of([$silver->id, $polished->id, $large->id])->value,
    ]);
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
