<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

it('takes the requested value when it belongs to the axis', function (): void {
    $axes = [AxisDefaults::of('axs_1', ['ovl_1', 'ovl_2'], 'ovl_1')];

    $resolved = AxisSelectionResolver::resolve($axes, ['axs_1' => 'ovl_2']);

    expect($resolved)->toBe(['axs_1' => 'ovl_2']);
});

it('falls back to the default when nothing is requested', function (): void {
    $axes = [AxisDefaults::of('axs_1', ['ovl_1', 'ovl_2'], 'ovl_1')];

    expect(AxisSelectionResolver::resolve($axes, []))->toBe(['axs_1' => 'ovl_1']);
});

it('falls back to the default when the requested id belongs to a different axis', function (): void {
    $axes = [AxisDefaults::of('axs_1', ['ovl_1', 'ovl_2'], 'ovl_1')];

    $resolved = AxisSelectionResolver::resolve($axes, ['axs_1' => 'ovl_from_another_axis']);

    expect($resolved)->toBe(['axs_1' => 'ovl_1']);
});

it('resolves every axis independently', function (): void {
    $axes = [
        AxisDefaults::of('axs_1', ['ovl_1', 'ovl_2'], 'ovl_1'),
        AxisDefaults::of('axs_2', ['ovl_3', 'ovl_4'], 'ovl_3'),
    ];

    $resolved = AxisSelectionResolver::resolve($axes, ['axs_2' => 'ovl_4']);

    expect($resolved)->toBe(['axs_1' => 'ovl_1', 'axs_2' => 'ovl_4']);
});
