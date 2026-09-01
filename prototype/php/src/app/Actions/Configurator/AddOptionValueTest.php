<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Domain\DomainRuleViolation;
use App\Models\OptionAxis;
use App\Models\PropertyValue;

it('adds a value to an axis, surcharge and default included', function (): void {
    $axis = OptionAxis::factory()->create();
    $catalogValue = PropertyValue::factory()->create();

    $value = app(AddOptionValue::class)($axis, 'Gold', 500, true, 2, $catalogValue);

    expect($value->axis_id)->toBe($axis->id)
        ->and($value->label)->toBe('Gold')
        ->and($value->surcharge_cents)->toBe(500)
        ->and($value->is_default)->toBeTrue()
        ->and($value->position)->toBe(2)
        ->and($value->property_value_id)->toBe($catalogValue->id);
});

it('defaults to no surcharge and no catalog value', function (): void {
    $value = app(AddOptionValue::class)(OptionAxis::factory()->create(), 'Silver');

    expect($value->surcharge_cents)->toBe(0)
        ->and($value->is_default)->toBeFalse()
        ->and($value->property_value_id)->toBeNull();
});

it('stores a standalone option’s price and forces its surcharge to zero', function (): void {
    $axis = OptionAxis::factory()->standalone()->create();

    $value = app(AddOptionValue::class)($axis, '8x10', surchargeCents: 999, priceCents: 1800);

    expect($value->price_cents)->toBe(1800)
        ->and($value->surcharge_cents)->toBe(0);
});

it('refuses a standalone option added with no price', function (): void {
    $axis = OptionAxis::factory()->standalone()->create();

    app(AddOptionValue::class)($axis, '8x10');
})->throws(DomainRuleViolation::class, 'Every option on this choice needs its own price.');

it('leaves price_cents null on an add-on option even if one is passed', function (): void {
    $axis = OptionAxis::factory()->addOn()->create();

    $value = app(AddOptionValue::class)($axis, 'Black frame', 3200, priceCents: 999);

    expect($value->price_cents)->toBeNull()
        ->and($value->surcharge_cents)->toBe(3200);
});

it('syncs the listing’s derived price to the new standalone default option', function (): void {
    $listing = $this->listing($this->seller(), ['price_cents' => 5000]);
    $axis = OptionAxis::factory()->standalone()->create(['listing_id' => $listing->id]);

    app(AddOptionValue::class)($axis, '8x10', isDefault: true, priceCents: 1800);

    expect($listing->refresh()->price_cents)->toBe(1800);
});
