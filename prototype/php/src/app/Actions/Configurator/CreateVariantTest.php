<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Domain\Configurator\ComboKey;
use App\Models\OptionAxis;
use App\Models\OptionValue;

it('creates one sparse variant with its combo key and option links', function (): void {
    $listing = $this->listing($this->seller());
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    $value = OptionValue::factory()->create(['axis_id' => $axis->id]);

    $variant = app(CreateVariant::class)($listing, [$value], priceOverrideCents: 9900);

    expect($variant->listing_id)->toBe($listing->id)
        ->and($variant->combo_key)->toBe(ComboKey::of([$value->id])->value)
        ->and($variant->price_override_cents)->toBe(9900)
        ->and($variant->options()->pluck('option_value_id')->all())->toBe([$value->id]);
});

it('creates the axis-free variant with the empty combo key', function (): void {
    $variant = app(CreateVariant::class)($this->listing($this->seller()), []);

    expect($variant->combo_key)->toBe('');
});

it('clears quantity on a serialized variant regardless of what was passed', function (): void {
    $variant = app(CreateVariant::class)($this->listing($this->seller()), [], quantity: 5, isSerialized: true);

    expect($variant->quantity)->toBeNull()
        ->and($variant->is_serialized)->toBeTrue();
});
