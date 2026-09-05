<?php

declare(strict_types=1);

namespace App\Configurator;

it('formats every spec entry into a humanized line', function (): void {
    expect(UnitSpecLines::format(['height_mm' => 205, 'condition' => 'mint']))
        ->toBe(['Height: 205 mm', 'Condition: mint']);
});

it('formats no lines for a piece with no specs', function (): void {
    expect(UnitSpecLines::format(null))->toBe([]);
});
