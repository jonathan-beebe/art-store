<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

use App\Domain\Money\Money;
use InvalidArgumentException;

it('prices a text answer at the modifier’s own add-on', function (): void {
    expect(ModifierAnswerPrice::forText(Money::fromCents(850))->amount->cents)->toBe(850);
});

it('prices a select answer at the chosen option’s add-on', function (): void {
    expect(ModifierAnswerPrice::forSelect(Money::fromCents(200))->amount->cents)->toBe(200);
});

it('prices a measurement answer on the rate times the value, rounded to the cent', function (): void {
    expect(ModifierAnswerPrice::forMeasurement(2.5, Money::fromCents(1200))->amount->cents)->toBe(3000)
        ->and(ModifierAnswerPrice::forMeasurement(2.333, Money::fromCents(100))->amount->cents)->toBe(233);
});

it('prices a measurement answer at zero with no rate set', function (): void {
    expect(ModifierAnswerPrice::forMeasurement(5.0, null)->amount->cents)->toBe(0);
});

it('refuses a negative measurement', function (): void {
    ModifierAnswerPrice::forMeasurement(-1.0, Money::fromCents(100));
})->throws(InvalidArgumentException::class);
