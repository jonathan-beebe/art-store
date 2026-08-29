<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

it('sorts numbered labels numerically, not as strings', function (): void {
    $labels = ['#1', '#10', '#11', '#12', '#2'];

    usort($labels, UnitLabelOrder::compare(...));

    expect($labels)->toBe(['#1', '#2', '#10', '#11', '#12']);
});

it('sorts plain-word labels alphabetically', function (): void {
    expect(UnitLabelOrder::compare('Apple', 'Banana'))->toBeLessThan(0)
        ->and(UnitLabelOrder::compare('Banana', 'Apple'))->toBeGreaterThan(0);
});

it('treats equal labels as equal', function (): void {
    expect(UnitLabelOrder::compare('#4', '#4'))->toBe(0);
});
