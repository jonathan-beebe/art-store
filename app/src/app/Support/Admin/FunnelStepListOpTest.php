<?php

declare(strict_types=1);

namespace App\Support\Admin;

it('appends a blank row on add_step', function (): void {
    expect(FunnelStepListOp::apply(['listing.view'], 'add_step'))->toBe(['listing.view', '']);
});

it('removes the row at the named index', function (): void {
    expect(FunnelStepListOp::apply(['a', 'b', 'c'], 'remove_step:1'))->toBe(['a', 'c']);
});

it('leaves the list unchanged when the removed index is out of range', function (): void {
    expect(FunnelStepListOp::apply(['a', 'b'], 'remove_step:5'))->toBe(['a', 'b']);
});

it('swaps a row with the one before it on move_up', function (): void {
    expect(FunnelStepListOp::apply(['a', 'b', 'c'], 'move_up:1'))->toBe(['b', 'a', 'c']);
});

it('leaves the list unchanged moving the first row up', function (): void {
    expect(FunnelStepListOp::apply(['a', 'b'], 'move_up:0'))->toBe(['a', 'b']);
});

it('swaps a row with the one after it on move_down', function (): void {
    expect(FunnelStepListOp::apply(['a', 'b', 'c'], 'move_down:0'))->toBe(['b', 'a', 'c']);
});

it('leaves the list unchanged moving the last row down', function (): void {
    expect(FunnelStepListOp::apply(['a', 'b'], 'move_down:1'))->toBe(['a', 'b']);
});

it('leaves the list unchanged for save and any other op', function (): void {
    expect(FunnelStepListOp::apply(['a', 'b'], 'save'))->toBe(['a', 'b'])
        ->and(FunnelStepListOp::apply(['a', 'b'], 'nonsense'))->toBe(['a', 'b']);
});
