<?php

declare(strict_types=1);

namespace App\Configurator;

use App\Actions\Configurator\AddOptionValue;
use App\Actions\Configurator\CreateOptionAxis;

it('defaults every axis to its seller-marked default value', function (): void {
    $listing = $this->listing($this->seller());
    $metal = app(CreateOptionAxis::class)($listing, 'Metal');
    $gold = app(AddOptionValue::class)($metal, 'Gold', 0, isDefault: true);
    app(AddOptionValue::class)($metal, 'Rose Gold', 800);

    $axisModels = $listing->optionAxes()->with('optionValues')->orderBy('position')->get();
    [$selected, $selectedOptionValues, $optionValueById] = SelectedAxisValues::resolve($axisModels, []);

    expect($selected)->toBe([$metal->id => $gold->id])
        ->and($selectedOptionValues)->toHaveCount(1)
        ->and($selectedOptionValues[0]->id)->toBe($gold->id)
        ->and($optionValueById)->toHaveKey($gold->id);
});

it('honors a valid raw selection over the axis default', function (): void {
    $listing = $this->listing($this->seller());
    $metal = app(CreateOptionAxis::class)($listing, 'Metal');
    app(AddOptionValue::class)($metal, 'Gold', 0, isDefault: true);
    $rose = app(AddOptionValue::class)($metal, 'Rose Gold', 800);

    $axisModels = $listing->optionAxes()->with('optionValues')->orderBy('position')->get();
    [$selected, $selectedOptionValues] = SelectedAxisValues::resolve($axisModels, [$metal->id => $rose->id]);

    expect($selected)->toBe([$metal->id => $rose->id])
        ->and($selectedOptionValues[0]->id)->toBe($rose->id);
});

it('skips an axis that has no option values yet', function (): void {
    $listing = $this->listing($this->seller());
    app(CreateOptionAxis::class)($listing, 'Empty axis');
    $color = app(CreateOptionAxis::class)($listing, 'Color');
    $red = app(AddOptionValue::class)($color, 'Red', 0, isDefault: true);

    $axisModels = $listing->optionAxes()->with('optionValues')->orderBy('position')->get();
    [$selected, $selectedOptionValues] = SelectedAxisValues::resolve($axisModels, []);

    expect($selected)->toBe([$color->id => $red->id])
        ->and($selectedOptionValues)->toHaveCount(1);
});

it('maps every option value on a defaulted axis by id, not only the selected one', function (): void {
    $listing = $this->listing($this->seller());
    $metal = app(CreateOptionAxis::class)($listing, 'Metal');
    $gold = app(AddOptionValue::class)($metal, 'Gold', 0, isDefault: true);
    $rose = app(AddOptionValue::class)($metal, 'Rose Gold', 800);

    $axisModels = $listing->optionAxes()->with('optionValues')->orderBy('position')->get();
    [, , $optionValueById] = SelectedAxisValues::resolve($axisModels, []);

    expect($optionValueById)->toHaveKeys([$gold->id, $rose->id]);
});
