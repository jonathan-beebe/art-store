<?php

declare(strict_types=1);

namespace App\Configurator;

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

it('drops a tampered axis selection whose key or value is not a string', function (): void {
    $input = ConfiguratorInput::fromRaw(
        [0 => 'value-id', 'axis-id' => 42, 'good-axis' => 'good-value'],
        null,
        [],
        '1',
    );

    expect($input->axisSelections)->toBe(['good-axis' => 'good-value']);
});

it('drops a nested-array axis value', function (): void {
    $input = ConfiguratorInput::fromRaw(
        ['axis-id' => ['nested' => 'value']],
        null,
        [],
        '1',
    );

    expect($input->axisSelections)->toBe([]);
});

it('drops a tampered modifier answer whose key or value is not a string', function (): void {
    $input = ConfiguratorInput::fromRaw(
        [],
        null,
        [0 => 'answer', 'modifier-id' => ['nested'], 'good-modifier' => 'answer'],
        '1',
    );

    expect($input->modifierAnswers)->toBe(['good-modifier' => 'answer']);
});

it('falls back to the default quantity when the raw quantity is not a digit string', function (): void {
    $input = ConfiguratorInput::fromRaw([], null, [], 'abc');

    expect($input->quantity)->toBe(1);
});
