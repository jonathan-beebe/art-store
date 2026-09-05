<?php

declare(strict_types=1);

use App\Domain\Configurator\ModifierKind;
use App\Domain\Configurator\PricedModifier;
use App\Domain\Configurator\PricedModifierOption;
use App\Domain\Money\Money;

$text = fn (int $addOnCents): PricedModifier => PricedModifier::of('mdf_text', 'Engraving', ModifierKind::Text, Money::fromCents($addOnCents), null, [], []);
$select = fn (): PricedModifier => PricedModifier::of('mdf_font', 'Font', ModifierKind::Select, Money::zero(), null, [
    PricedModifierOption::of('mop_serif', 'Serif', Money::fromCents(0)),
    PricedModifierOption::of('mop_script', 'Script', Money::fromCents(300)),
], []);
$measurement = fn (): PricedModifier => PricedModifier::of('mdf_len', 'Chain length', ModifierKind::Measurement, Money::zero(), Money::fromCents(250), [], []);

it('applies to every selection when it has no scope', function (): void {
    expect(PricedModifier::of('mdf', 'p', ModifierKind::Text, Money::zero(), null, [], [])->appliesTo(['ovl_any']))->toBeTrue();
});

it('applies only when a scoped option value is selected', function (): void {
    $scoped = PricedModifier::of('mdf', 'p', ModifierKind::Text, Money::zero(), null, [], ['ovl_personalized']);

    expect($scoped->appliesTo(['ovl_personalized', 'ovl_other']))->toBeTrue()
        ->and($scoped->appliesTo(['ovl_other']))->toBeFalse();
});

it('prices a text answer at its flat add-on', function () use ($text): void {
    expect($text(400)->priceAnswer('Congrats!'))->toBeMoney(400)
        ->and($text(400)->answerLabel('Congrats!'))->toBe('Engraving');
});

it('prices a select answer at the chosen option and names the choice', function () use ($select): void {
    expect($select()->priceAnswer('mop_script'))->toBeMoney(300)
        ->and($select()->answerLabel('mop_script'))->toBe('Font: Script');
});

it('charges nothing and names only the prompt for a select answer naming no option', function () use ($select): void {
    expect($select()->priceAnswer('mop_missing'))->toBeMoney(0)
        ->and($select()->answerLabel('mop_missing'))->toBe('Font');
});

it('prices a measurement answer on its rate and reads a non-number as zero', function () use ($measurement): void {
    expect($measurement()->priceAnswer('4'))->toBeMoney(1000)
        ->and($measurement()->priceAnswer('four'))->toBeMoney(0);
});
