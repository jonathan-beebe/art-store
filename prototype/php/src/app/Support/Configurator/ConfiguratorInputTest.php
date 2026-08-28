<?php

declare(strict_types=1);

namespace App\Support\Configurator;

use Illuminate\Http\Request;

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

it('falls back to the given defaults when the request carries no axis, unit, or quantity', function (): void {
    $request = Request::create('/seller/listings/lst_1/variants/vrt_1/units');

    $input = ConfiguratorInput::fromQuery($request, ['axs_1' => 'ovl_1'], 'unt_1', 5);

    expect($input->axisSelections)->toBe(['axs_1' => 'ovl_1'])
        ->and($input->unitId)->toBe('unt_1')
        ->and($input->quantity)->toBe(5);
});

it('prefers the request over the given defaults once the request carries a value', function (): void {
    $request = Request::create('/art/print?'.http_build_query(['axis' => ['axs_1' => 'ovl_2'], 'unit' => 'unt_2', 'quantity' => '3']));

    $input = ConfiguratorInput::fromQuery($request, ['axs_1' => 'ovl_1'], 'unt_1', 5);

    expect($input->axisSelections)->toBe(['axs_1' => 'ovl_2'])
        ->and($input->unitId)->toBe('unt_2')
        ->and($input->quantity)->toBe(3);
});
