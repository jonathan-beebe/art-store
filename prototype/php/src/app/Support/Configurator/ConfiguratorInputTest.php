<?php

declare(strict_types=1);

namespace App\Support\Configurator;

it('carries the raw choices off a request', function (): void {
    $input = ConfiguratorInput::of(['axs_1' => 'ovl_1'], 'unt_1', ['mdf_1' => 'hello'], 3);

    expect($input->axisSelections)->toBe(['axs_1' => 'ovl_1'])
        ->and($input->unitId)->toBe('unt_1')
        ->and($input->modifierAnswers)->toBe(['mdf_1' => 'hello'])
        ->and($input->quantity)->toBe(3);
});

it('floors the quantity at one', function (): void {
    expect(ConfiguratorInput::of([], null, [], 0)->quantity)->toBe(1);
});
