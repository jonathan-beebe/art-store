<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

it('humanizes a known measurement key with its unit', function (): void {
    expect(UnitSpecLabel::format('height_mm', 205))->toBe('Height: 205 mm')
        ->and(UnitSpecLabel::format('weight_g', 310))->toBe('Weight: 310 g');
});

it('title-cases an unknown key with no unit suffix', function (): void {
    expect(UnitSpecLabel::format('condition_grade', 'A'))->toBe('Condition Grade: A')
        ->and(UnitSpecLabel::format('material', 'Brass'))->toBe('Material: Brass');
});

it('formats a boolean value as a word', function (): void {
    expect(UnitSpecLabel::format('signed', true))->toBe('Signed: true')
        ->and(UnitSpecLabel::format('signed', false))->toBe('Signed: false');
});

it('formats a float value without a trailing unit misparse', function (): void {
    expect(UnitSpecLabel::format('diameter_in', 3.5))->toBe('Diameter: 3.5 in');
});
