<?php

declare(strict_types=1);

namespace App\Configurator;

use App\Actions\Configurator\AddModifierOption;
use App\Actions\Configurator\AddOptionValue;
use App\Actions\Configurator\AddQuantityBreak;
use App\Actions\Configurator\AddUnit;
use App\Actions\Configurator\CreateModifier;
use App\Actions\Configurator\CreateOptionAxis;
use App\Actions\Configurator\CreateVariant;
use App\Actions\Configurator\GenerateVariants;
use App\Actions\Configurator\SetModifierScope;
use App\Domain\Configurator\ModifierKind;
use App\Domain\Configurator\PricingMode;
use LogicException;

it('says a plain listing has no configurator', function (): void {
    $listing = $this->listing($this->seller());

    expect(ConfiguratorPageResolver::hasConfigurator($listing))->toBeFalse();
});

it('says a listing with only a quantity discount still has a configurator', function (): void {
    $listing = $this->listing($this->seller());
    app(AddQuantityBreak::class)($listing, 50, 1000);

    expect(ConfiguratorPageResolver::hasConfigurator($listing))->toBeTrue();

    $configuration = ConfiguratorPageResolver::resolve($listing, ConfiguratorInput::of([], null, [], 1));

    expect($configuration->hasConfigurator)->toBeTrue();
});

it('preselects each axis default and prices the page concretely at first paint', function (): void {
    $listing = $this->listing($this->seller(), ['price_cents' => 12000]);
    $metal = app(CreateOptionAxis::class)($listing, 'Metal');
    app(AddOptionValue::class)($metal, 'Gold', 0, isDefault: true);
    $rose = app(AddOptionValue::class)($metal, 'Rose Gold', 800);
    app(GenerateVariants::class)($listing);

    expect(ConfiguratorPageResolver::hasConfigurator($listing))->toBeTrue();

    $configuration = ConfiguratorPageResolver::resolve($listing, ConfiguratorInput::of([], null, [], 1));

    expect($configuration->breakdown->total()->cents)->toBe(12000)
        ->and($configuration->axes[0]['options'][0]['selected'])->toBeTrue()
        ->and($configuration->canAddToCart)->toBeTrue();

    $withRoseGold = ConfiguratorPageResolver::resolve($listing, ConfiguratorInput::of([$metal->id => $rose->id], null, [], 1));

    expect($withRoseGold->breakdown->total()->cents)->toBe(12800);
});

it('greys out a combination the seller never created, with a not-offered reason', function (): void {
    $listing = $this->listing($this->seller(), ['price_cents' => 80000]);
    $length = app(CreateOptionAxis::class)($listing, 'Length');
    $l36 = app(AddOptionValue::class)($length, '36 in', 0, isDefault: true);
    $l48 = app(AddOptionValue::class)($length, '48 in', 0);
    $width = app(CreateOptionAxis::class)($listing, 'Width');
    $w24 = app(AddOptionValue::class)($width, '24 in', 0, isDefault: true);
    $w30 = app(AddOptionValue::class)($width, '30 in', 0);

    $createVariant = app(CreateVariant::class);
    $createVariant($listing, [$l36, $w24], priceOverrideCents: 80000);
    $createVariant($listing, [$l48, $w30], priceOverrideCents: 110000);

    $configuration = ConfiguratorPageResolver::resolve($listing, ConfiguratorInput::of([$length->id => $l48->id], null, [], 1));

    expect($configuration->canAddToCart)->toBeFalse()
        ->and($configuration->unavailableReason)->toBe('not offered');

    $widthOptions = collect($configuration->axes[1]['options'])->keyBy('id');
    $w24Option = $widthOptions[$w24->id] ?? throw new LogicException('Expected the width axis to offer 24 in.');
    $w30Option = $widthOptions[$w30->id] ?? throw new LogicException('Expected the width axis to offer 30 in.');

    expect($w24Option['selectable'])->toBeFalse()
        ->and($w24Option['reason'])->toBe('not offered')
        ->and($w30Option['selectable'])->toBeTrue();
});

it('greys out an offered combination whose variant is disabled', function (): void {
    $listing = $this->listing($this->seller());
    $axis = app(CreateOptionAxis::class)($listing, 'Color');
    $red = app(AddOptionValue::class)($axis, 'Red', 0, isDefault: true);
    $blue = app(AddOptionValue::class)($axis, 'Blue', 0);
    app(GenerateVariants::class)($listing);
    $listing->variants()->where('combo_key', $blue->id)->sole()->update(['enabled' => false]);

    $configuration = ConfiguratorPageResolver::resolve($listing, ConfiguratorInput::of([$axis->id => $blue->id], null, [], 1));

    expect($configuration->canAddToCart)->toBeFalse()
        ->and($configuration->unavailableReason)->toBe('not offered');
});

it('greys out an enabled variant with no stock left as out of stock', function (): void {
    $listing = $this->listing($this->seller());
    $axis = app(CreateOptionAxis::class)($listing, 'Color');
    $red = app(AddOptionValue::class)($axis, 'Red', 0, isDefault: true);
    $blue = app(AddOptionValue::class)($axis, 'Blue', 0);
    app(GenerateVariants::class)($listing);
    $listing->variants()->where('combo_key', $blue->id)->sole()->update(['quantity' => 0]);

    $configuration = ConfiguratorPageResolver::resolve($listing, ConfiguratorInput::of([$axis->id => $blue->id], null, [], 1));

    expect($configuration->canAddToCart)->toBeFalse()
        ->and($configuration->unavailableReason)->toBe('out of stock');
});

it('shows a modifier only once its scope matches the selection, mug style', function (): void {
    $listing = $this->listing($this->seller(), ['price_cents' => 1800]);
    $personalization = app(CreateOptionAxis::class)($listing, 'Personalization');
    $blank = app(AddOptionValue::class)($personalization, 'Blank', 0, isDefault: true);
    $personalized = app(AddOptionValue::class)($personalization, 'Personalized', 300);
    app(GenerateVariants::class)($listing);
    $text = app(CreateModifier::class)($listing, ModifierKind::Text, 'Personalization Text', required: true, charLimit: 16);
    app(SetModifierScope::class)($text, [$personalized]);

    $blankConfiguration = ConfiguratorPageResolver::resolve($listing, ConfiguratorInput::of([], null, [], 1));
    expect($blankConfiguration->modifiers)->toBeEmpty()
        ->and($blankConfiguration->breakdown->total()->cents)->toBe(1800);

    $personalizedConfiguration = ConfiguratorPageResolver::resolve(
        $listing,
        ConfiguratorInput::of([$personalization->id => $personalized->id], null, [$text->id => 'Ada'], 1),
    );
    expect($personalizedConfiguration->modifiers)->toHaveCount(1)
        ->and($personalizedConfiguration->modifiers[0]['answer'])->toBe('Ada')
        ->and($personalizedConfiguration->breakdown->total()->cents)->toBe(2100);
});

it('defaults a select modifier to its first option and prices accordingly', function (): void {
    $listing = $this->listing($this->seller());
    $font = app(CreateModifier::class)($listing, ModifierKind::Select, 'Engraving Font', required: true);
    $block = app(AddModifierOption::class)($font, 'Block', 0, 0);
    $script = app(AddModifierOption::class)($font, 'Script', 200, 1);

    $configuration = ConfiguratorPageResolver::resolve($listing, ConfiguratorInput::of([], null, [], 1));

    expect($configuration->modifiers[0]['answer'])->toBe($block->id);

    $withScript = ConfiguratorPageResolver::resolve($listing, ConfiguratorInput::of([], null, [$font->id => $script->id], 1));
    expect($withScript->breakdown->total()->cents)->toBe($listing->price_cents + 200);
});

it('renders a serialized variant as a unit picker excluding sold units', function (): void {
    $listing = $this->listing($this->seller(), ['price_cents' => 4500]);
    $variant = app(CreateVariant::class)($listing, [], isSerialized: true);
    $addUnit = app(AddUnit::class);
    $one = $addUnit($variant, '#1', priceOverrideCents: 3500);
    $two = $addUnit($variant, '#2');
    $sold = $addUnit($variant, '#3');
    $sold->update(['state' => 'sold']);

    $configuration = ConfiguratorPageResolver::resolve($listing, ConfiguratorInput::of([], null, [], 1));

    expect($configuration->isSerialized)->toBeTrue()
        ->and($configuration->units)->toHaveCount(2)
        ->and(collect($configuration->units)->pluck('id'))->not->toContain($sold->id)
        ->and($configuration->selectedUnitId)->toBe($one->id)
        ->and($configuration->breakdown->total()->cents)->toBe(3500);

    $withUnitTwo = ConfiguratorPageResolver::resolve($listing, ConfiguratorInput::of([], $two->id, [], 1));
    expect($withUnitTwo->breakdown->total()->cents)->toBe(4500);
});

it('folds a standalone selection and an add-on selection into a serialized unit’s price', function (): void {
    $listing = $this->listing($this->seller(), ['price_cents' => 0]);
    $size = app(CreateOptionAxis::class)($listing, 'Size', pricingMode: PricingMode::Standalone);
    $eightByTen = app(AddOptionValue::class)($size, '8x10', isDefault: true, priceCents: 1800);
    $frame = app(CreateOptionAxis::class)($listing, 'Frame');
    $framed = app(AddOptionValue::class)($frame, 'Framed', 3200, isDefault: true);
    $variant = app(CreateVariant::class)($listing, [$eightByTen, $framed], isSerialized: true);
    app(AddUnit::class)($variant, '#1');

    $configuration = ConfiguratorPageResolver::resolve(
        $listing,
        ConfiguratorInput::of([$size->id => $eightByTen->id, $frame->id => $framed->id], null, [], 1),
    );

    expect($configuration->units[0]['price']->cents)->toBe(1800 + 3200);
});

it('routes a serialized variant’s units through to the resolved configuration', function (): void {
    $listing = $this->listing($this->seller());
    $variant = app(CreateVariant::class)($listing, [], isSerialized: true);
    app(AddUnit::class)($variant, '#1');
    app(AddUnit::class)($variant, '#2');

    $configuration = ConfiguratorPageResolver::resolve($listing, ConfiguratorInput::of([], null, [], 1));

    expect($configuration->isSerialized)->toBeTrue()
        ->and($configuration->units)->toHaveCount(2);
});

it('reports out of stock once every unit is sold', function (): void {
    $listing = $this->listing($this->seller());
    $variant = app(CreateVariant::class)($listing, [], isSerialized: true);
    $only = app(AddUnit::class)($variant, '#1');
    $only->update(['state' => 'sold']);

    $configuration = ConfiguratorPageResolver::resolve($listing, ConfiguratorInput::of([], null, [], 1));

    expect($configuration->units)->toBeEmpty()
        ->and($configuration->selectedUnitId)->toBeNull()
        ->and($configuration->canAddToCart)->toBeFalse()
        ->and($configuration->unavailableReason)->toBe('out of stock');
});

it('shows the quantity-break table and applies the best tier live', function (): void {
    $listing = $this->listing($this->seller(), ['price_cents' => 300]);
    app(AddQuantityBreak::class)($listing, 50, 500);
    app(AddQuantityBreak::class)($listing, 100, 1000);

    $configuration = ConfiguratorPageResolver::resolve($listing, ConfiguratorInput::of([], null, [], 150));

    expect($configuration->quantityTiers)->toHaveCount(2)
        ->and($configuration->quantityTiers[1]['active'])->toBeTrue()
        ->and($configuration->quantityTiers[0]['active'])->toBeFalse();
});

it('skips an axis that has no option values yet', function (): void {
    $listing = $this->listing($this->seller());
    app(CreateOptionAxis::class)($listing, 'Empty axis');
    $color = app(CreateOptionAxis::class)($listing, 'Color');
    app(AddOptionValue::class)($color, 'Red', 0, isDefault: true);
    app(GenerateVariants::class)($listing);

    $configuration = ConfiguratorPageResolver::resolve($listing, ConfiguratorInput::of([], null, [], 1));

    expect($configuration->axes)->toHaveCount(1)
        ->and($configuration->axes[0]['name'])->toBe('Color');
});

it('formats a measurement modifier’s answer, falling back to blank for a non-numeric one', function (): void {
    $listing = $this->listing($this->seller(), ['price_cents' => 4500]);
    $length = app(CreateModifier::class)($listing, ModifierKind::Measurement, 'Engraved length', instructions: 'In inches.');
    $length->update(['unit' => 'in', 'min_value' => 0, 'max_value' => 20, 'rate_cents_per_unit' => 150]);

    $unanswered = ConfiguratorPageResolver::resolve($listing, ConfiguratorInput::of([], null, [], 1));
    expect($unanswered->modifiers[0]['answer'])->toBe('');

    $answered = ConfiguratorPageResolver::resolve($listing, ConfiguratorInput::of([], null, [$length->id => '4'], 1));
    expect($answered->modifiers[0]['answer'])->toBe('4');

    $nonNumeric = ConfiguratorPageResolver::resolve($listing, ConfiguratorInput::of([], null, [$length->id => 'not-a-number'], 1));
    expect($nonNumeric->modifiers[0]['answer'])->toBe('');
});

it('carries a configuration snapshot and fingerprint answers for the add-to-cart action', function (): void {
    $listing = $this->listing($this->seller());
    $metal = app(CreateOptionAxis::class)($listing, 'Metal');
    $gold = app(AddOptionValue::class)($metal, 'Gold', 0, isDefault: true);
    app(GenerateVariants::class)($listing);
    $text = app(CreateModifier::class)($listing, ModifierKind::Text, 'Note');

    $configuration = ConfiguratorPageResolver::resolve($listing, ConfiguratorInput::of([], null, [$text->id => 'Hi'], 1));

    expect($configuration->configurationSnapshot)->toBe([
        ['axisId' => $metal->id, 'axisName' => 'Metal', 'optionValueId' => $gold->id, 'optionValueLabel' => 'Gold'],
    ])
        ->and($configuration->answersSnapshot)->toBe([$text->id => ['prompt' => 'Note', 'answer' => 'Hi', 'raw' => 'Hi']])
        ->and($configuration->fingerprintAnswers)->toBe([$text->id => 'Hi']);
});
