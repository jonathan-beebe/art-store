<?php

declare(strict_types=1);

namespace App\Support\Configurator;

it('turns existing specs into prefilled rows padded with blanks', function (): void {
    expect(UnitSpecRows::forEditing(['Height' => '26 cm']))->toBe([
        ['label' => 'Height', 'value' => '26 cm'],
        ['label' => '', 'value' => ''],
        ['label' => '', 'value' => ''],
        ['label' => '', 'value' => ''],
    ]);
});

it('stringifies non-string spec values', function (): void {
    expect(UnitSpecRows::forEditing(['height_mm' => 205, 'polished' => true]))->toBe([
        ['label' => 'height_mm', 'value' => '205'],
        ['label' => 'polished', 'value' => 'true'],
        ['label' => '', 'value' => ''],
        ['label' => '', 'value' => ''],
        ['label' => '', 'value' => ''],
    ]);
});

it('offers only blank rows for a piece with no specs yet', function (): void {
    expect(UnitSpecRows::forEditing(null))->toBe([
        ['label' => '', 'value' => ''],
        ['label' => '', 'value' => ''],
        ['label' => '', 'value' => ''],
    ]);
});
