<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Domain\Configurator\ModifierKind;

it('adds a text modifier with its pricing and shape', function (): void {
    $listing = $this->listing($this->seller());

    $modifier = app(CreateModifier::class)($listing, ModifierKind::Text, 'Personalization text', 'Up to 20 characters', true, 1, 850, 20);

    expect($modifier->listing_id)->toBe($listing->id)
        ->and($modifier->kind)->toBe(ModifierKind::Text)
        ->and($modifier->prompt)->toBe('Personalization text')
        ->and($modifier->instructions)->toBe('Up to 20 characters')
        ->and($modifier->required)->toBeTrue()
        ->and($modifier->position)->toBe(1)
        ->and($modifier->add_on_price_cents)->toBe(850)
        ->and($modifier->char_limit)->toBe(20);
});

it('adds a measurement modifier with its unit, range, and rate', function (): void {
    $listing = $this->listing($this->seller());

    $modifier = app(CreateModifier::class)(
        $listing,
        ModifierKind::Measurement,
        'Custom length',
        unit: 'cm',
        minValue: 10.0,
        maxValue: 200.0,
        rateCentsPerUnit: 1200,
    );

    expect($modifier->kind)->toBe(ModifierKind::Measurement)
        ->and($modifier->unit)->toBe('cm')
        ->and($modifier->min_value)->toBe(10.0)
        ->and($modifier->max_value)->toBe(200.0)
        ->and($modifier->rate_cents_per_unit)->toBe(1200);
});

it('defaults to optional, unpriced, unlimited', function (): void {
    $modifier = app(CreateModifier::class)($this->listing($this->seller()), ModifierKind::Select, 'Font');

    expect($modifier->required)->toBeFalse()
        ->and($modifier->add_on_price_cents)->toBe(0)
        ->and($modifier->char_limit)->toBeNull();
});
