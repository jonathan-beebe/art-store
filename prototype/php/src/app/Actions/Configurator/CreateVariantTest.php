<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Domain\Configurator\ComboKey;
use App\Domain\DomainRuleViolation;
use App\Models\OptionAxis;
use App\Models\OptionValue;
use App\Models\Variant;

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

it('carries an optional sku', function (): void {
    $withSku = app(CreateVariant::class)($this->listing($this->seller()), [], sku: 'RING-GOLD-BOTH');
    $withoutSku = app(CreateVariant::class)($this->listing($this->seller()), []);

    expect($withSku->sku)->toBe('RING-GOLD-BOTH')
        ->and($withoutSku->sku)->toBeNull();
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

it('persists enabled: false at creation', function (): void {
    $variant = app(CreateVariant::class)($this->listing($this->seller()), [], enabled: false);

    expect($variant->enabled)->toBeFalse()
        ->and($variant->fresh()?->enabled)->toBeFalse();
});

it('refuses a combination that already has a variant row instead of a raw constraint violation', function (): void {
    $listing = $this->listing($this->seller());
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id, 'name' => 'Metal']);
    $value = OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => 'Gold']);
    app(CreateVariant::class)($listing, [$value]);

    $create = fn () => app(CreateVariant::class)($listing, [$value]);

    expect($create)->toThrow(DomainRuleViolation::class, 'Gold already exists — edit its row in the grid above.');
    expect(Variant::where('listing_id', $listing->id)->count())->toBe(1);
});

it('refuses a second axis-free variant the same way', function (): void {
    $listing = $this->listing($this->seller());
    app(CreateVariant::class)($listing, []);

    $create = fn () => app(CreateVariant::class)($listing, []);

    expect($create)->toThrow(DomainRuleViolation::class, 'This combination already exists — edit its row in the grid above.');
});
