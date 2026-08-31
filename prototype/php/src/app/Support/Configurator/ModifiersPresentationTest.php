<?php

declare(strict_types=1);

namespace App\Support\Configurator;

use App\Actions\Configurator\AddModifierOption;
use App\Actions\Configurator\AddOptionValue;
use App\Actions\Configurator\CreateModifier;
use App\Actions\Configurator\CreateOptionAxis;
use App\Actions\Configurator\ScopeModifier;
use App\Domain\Configurator\ModifierKind;

it('leaves out a modifier whose scope excludes the current selection', function (): void {
    $listing = $this->listing($this->seller());
    $axis = app(CreateOptionAxis::class)($listing, 'Personalization');
    app(AddOptionValue::class)($axis, 'Blank', 0, isDefault: true);
    $personalized = app(AddOptionValue::class)($axis, 'Personalized', 300);
    $text = app(CreateModifier::class)($listing, ModifierKind::Text, 'Note');
    app(ScopeModifier::class)($text, [$personalized]);

    $modifierModels = $listing->modifiers()->with('options')->orderBy('position')->get();
    [$presentation] = ModifiersPresentation::build($modifierModels, [], []);

    expect($presentation)->toBeEmpty();
});

it('defaults a select modifier to its first option and prices a chosen one', function (): void {
    $listing = $this->listing($this->seller());
    $font = app(CreateModifier::class)($listing, ModifierKind::Select, 'Engraving Font', required: true);
    $block = app(AddModifierOption::class)($font, 'Block', 0, 0);
    $script = app(AddModifierOption::class)($font, 'Script', 200, 1);

    $modifierModels = $listing->modifiers()->with('options')->orderBy('position')->get();

    [$presentation] = ModifiersPresentation::build($modifierModels, [], []);
    expect($presentation[0]['answer'])->toBe($block->id)
        ->and($presentation[0]['options'])->toHaveCount(2)
        ->and($presentation[0]['options'][1]['id'])->toBe($script->id)
        ->and($presentation[0]['options'][1]['selected'])->toBeFalse();

    [$withScript] = ModifiersPresentation::build($modifierModels, [], [$font->id => $script->id]);
    expect($withScript[0]['answer'])->toBe($script->id)
        ->and($withScript[0]['delta']->cents)->toBe(200);
});

it('trims a text answer and prices its flat add-on only once non-blank', function (): void {
    $listing = $this->listing($this->seller());
    $modifier = app(CreateModifier::class)($listing, ModifierKind::Text, 'Note', addOnPriceCents: 500);

    $modifierModels = $listing->modifiers()->with('options')->orderBy('position')->get();

    [$blank, $blankRaw, $blankSnapshot] = ModifiersPresentation::build($modifierModels, [], []);
    expect($blank[0]['answer'])->toBe('')
        ->and($blank[0]['delta']->cents)->toBe(0)
        ->and($blankRaw)->toBe([])
        ->and($blankSnapshot)->toBe([]);

    [$answered, $rawAnswers, $answersSnapshot] = ModifiersPresentation::build($modifierModels, [], [$modifier->id => '  Congrats!  ']);
    expect($answered[0]['answer'])->toBe('Congrats!')
        ->and($answered[0]['delta']->cents)->toBe(500)
        ->and($rawAnswers)->toBe([$modifier->id => 'Congrats!'])
        ->and($answersSnapshot)->toBe([$modifier->id => ['prompt' => 'Note', 'answer' => 'Congrats!', 'raw' => 'Congrats!']]);
});

it('prices a measurement answer on its rate and shows the unit, blank when non-numeric', function (): void {
    $listing = $this->listing($this->seller());
    $modifier = app(CreateModifier::class)($listing, ModifierKind::Measurement, 'Engraved length', instructions: 'In inches.', unit: 'in', minValue: 0, maxValue: 20, rateCentsPerUnit: 150);

    $modifierModels = $listing->modifiers()->with('options')->orderBy('position')->get();

    [$unanswered] = ModifiersPresentation::build($modifierModels, [], []);
    expect($unanswered[0]['answer'])->toBe('')
        ->and($unanswered[0]['delta']->cents)->toBe(0);

    [$nonNumeric] = ModifiersPresentation::build($modifierModels, [], [$modifier->id => 'not-a-number']);
    expect($nonNumeric[0]['answer'])->toBe('');

    [$answered, , $answersSnapshot] = ModifiersPresentation::build($modifierModels, [], [$modifier->id => '4']);
    expect($answered[0]['answer'])->toBe('4')
        ->and($answered[0]['delta']->cents)->toBe(600)
        ->and($answersSnapshot[$modifier->id]['answer'])->toBe('4 in');
});
