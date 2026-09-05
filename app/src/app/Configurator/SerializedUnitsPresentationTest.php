<?php

declare(strict_types=1);

namespace App\Configurator;

use App\Actions\Configurator\AddOptionValue;
use App\Actions\Configurator\AddUnit;
use App\Actions\Configurator\CreateOptionAxis;
use App\Actions\Configurator\CreateVariant;
use App\Domain\Configurator\PricingMode;

it('offers no units for a listing with no matched serialized variant', function (): void {
    $listing = $this->listing($this->seller());

    [$presentation, $selectedUnitId] = SerializedUnitsPresentation::build($listing, null, false, [], null, $listing->optionAxes()->get()->keyBy('id'));

    expect($presentation)->toBe([])
        ->and($selectedUnitId)->toBeNull();
});

it('orders units naturally by label and excludes sold units', function (): void {
    $listing = $this->listing($this->seller());
    $variant = app(CreateVariant::class)($listing, [], isSerialized: true);
    $addUnit = app(AddUnit::class);
    $addUnit($variant, '#10');
    $addUnit($variant, '#2');
    $sold = $addUnit($variant, '#1');
    $sold->update(['state' => 'sold']);

    $axisById = $listing->optionAxes()->get()->keyBy('id');
    [$presentation, $selectedUnitId] = SerializedUnitsPresentation::build($listing, $variant, true, [], null, $axisById);

    expect(collect($presentation)->pluck('label')->all())->toBe(['#2', '#10'])
        ->and($selectedUnitId)->toBe($presentation[0]['id']);
});

it('selects the requested unit when still available, falling back to the first available one', function (): void {
    $listing = $this->listing($this->seller());
    $variant = app(CreateVariant::class)($listing, [], isSerialized: true);
    $addUnit = app(AddUnit::class);
    $one = $addUnit($variant, '#1');
    $two = $addUnit($variant, '#2');
    $sold = $addUnit($variant, '#3');
    $sold->update(['state' => 'sold']);

    $axisById = $listing->optionAxes()->get()->keyBy('id');

    [, $requested] = SerializedUnitsPresentation::build($listing, $variant, true, [], $two->id, $axisById);
    expect($requested)->toBe($two->id);

    [, $fallback] = SerializedUnitsPresentation::build($listing, $variant, true, [], $sold->id, $axisById);
    expect($fallback)->toBe($one->id);
});

it('folds a standalone selection and an add-on selection into each unit’s price', function (): void {
    $listing = $this->listing($this->seller(), ['price_cents' => 0]);
    $size = app(CreateOptionAxis::class)($listing, 'Size', pricingMode: PricingMode::Standalone);
    $eightByTen = app(AddOptionValue::class)($size, '8x10', isDefault: true, priceCents: 1800);
    $frame = app(CreateOptionAxis::class)($listing, 'Frame');
    $framed = app(AddOptionValue::class)($frame, 'Framed', 3200, isDefault: true);
    $variant = app(CreateVariant::class)($listing, [$eightByTen, $framed], isSerialized: true);
    app(AddUnit::class)($variant, '#1');

    $axisById = $listing->optionAxes()->get()->keyBy('id');
    [$presentation] = SerializedUnitsPresentation::build($listing, $variant, true, [$eightByTen, $framed], null, $axisById);

    expect($presentation[0]['price']->cents)->toBe(1800 + 3200);
});

it('humanizes each unit’s spec lines and carries its condition note', function (): void {
    $listing = $this->listing($this->seller());
    $variant = app(CreateVariant::class)($listing, [], isSerialized: true);
    app(AddUnit::class)($variant, '#1', conditionNote: 'Small chip on the base.', specs: ['height_mm' => 205, 'weight_g' => 310]);

    $axisById = $listing->optionAxes()->get()->keyBy('id');
    [$presentation] = SerializedUnitsPresentation::build($listing, $variant, true, [], null, $axisById);

    expect($presentation[0]['conditionNote'])->toBe('Small chip on the base.')
        ->and($presentation[0]['specLines'])->toBe(['Height: 205 mm', 'Weight: 310 g']);
});
