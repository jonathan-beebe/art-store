<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Domain\Configurator\PricingMode;
use App\Models\Property;

it('adds an axis to a listing, with an optional catalog property', function (): void {
    $listing = $this->listing($this->seller());
    $property = Property::factory()->create();

    $axis = app(CreateOptionAxis::class)($listing, 'Metal', $property, 1);

    expect($axis->listing_id)->toBe($listing->id)
        ->and($axis->property_id)->toBe($property->id)
        ->and($axis->name)->toBe('Metal')
        ->and($axis->position)->toBe(1);
});

it('adds a custom, label-only axis with no property', function (): void {
    $axis = app(CreateOptionAxis::class)($this->listing($this->seller()), 'Engraving Placement');

    expect($axis->property_id)->toBeNull();
});

it('defaults a new axis to add-on pricing', function (): void {
    $axis = app(CreateOptionAxis::class)($this->listing($this->seller()), 'Frame');

    expect($axis->pricing_mode)->toBe(PricingMode::AddOn);
});

it('creates a standalone-priced axis when asked', function (): void {
    $axis = app(CreateOptionAxis::class)($this->listing($this->seller()), 'Size', pricingMode: PricingMode::Standalone);

    expect($axis->pricing_mode)->toBe(PricingMode::Standalone);
});

it('syncs the listing’s derived price once it gains a standalone axis', function (): void {
    $listing = $this->listing($this->seller(), ['price_cents' => 45000]);

    app(CreateOptionAxis::class)($listing, 'Size', pricingMode: PricingMode::Standalone);

    // The new axis has no options yet, so its default contributes nothing —
    // the listing's seller-typed price is overwritten as soon as any axis is standalone.
    expect($listing->refresh()->price_cents)->toBe(0);
});
