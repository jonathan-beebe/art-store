<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Domain\Configurator\PricingMode;
use App\Domain\DomainRuleViolation;
use App\Models\OptionAxis;
use App\Models\OptionValue;
use App\Models\Property;

it('renames an axis, reassigns its property, and repositions it', function (): void {
    $axis = OptionAxis::factory()->create(['name' => 'Metal', 'position' => 0]);
    $property = Property::factory()->create();

    $updated = app(UpdateOptionAxis::class)($axis, 'Finish', $property, 3, $axis->pricing_mode);

    expect($updated->name)->toBe('Finish')
        ->and($updated->property_id)->toBe($property->id)
        ->and($updated->position)->toBe(3);
});

it('clears the property when the axis becomes custom', function (): void {
    $axis = OptionAxis::factory()->create(['property_id' => Property::factory()->create()->id]);

    $updated = app(UpdateOptionAxis::class)($axis, 'Custom label', null, 0, $axis->pricing_mode);

    expect($updated->property_id)->toBeNull();
});

it('changes the pricing mode of an axis that has no options yet', function (): void {
    $axis = OptionAxis::factory()->addOn()->create();

    $updated = app(UpdateOptionAxis::class)($axis, $axis->name, null, 0, PricingMode::Standalone);

    expect($updated->pricing_mode)->toBe(PricingMode::Standalone);
});

it('refuses to change the pricing mode once the axis has an option', function (): void {
    $axis = OptionAxis::factory()->addOn()->create();
    OptionValue::factory()->create(['axis_id' => $axis->id]);

    app(UpdateOptionAxis::class)($axis, $axis->name, null, 0, PricingMode::Standalone);
})->throws(DomainRuleViolation::class, "This choice already has options — its pricing can't change. Remove the options first, or add a new choice.");

it('allows an update that keeps the same pricing mode even with options present', function (): void {
    $axis = OptionAxis::factory()->addOn()->create();
    OptionValue::factory()->create(['axis_id' => $axis->id]);

    $updated = app(UpdateOptionAxis::class)($axis, 'Renamed', null, 0, $axis->pricing_mode);

    expect($updated->name)->toBe('Renamed');
});
