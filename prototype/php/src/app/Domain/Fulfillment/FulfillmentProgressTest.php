<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment;

/**
 * @param  list<string>  $keys
 * @return list<FlowStep>
 */
function flowOf(array $keys): array
{
    return array_map(
        fn (int $index): FlowStep => new FlowStep(
            id: 'ffs_'.$keys[$index],
            key: $keys[$index],
            label: ucfirst($keys[$index]),
            action: $index === 0 ? FlowStepAction::PrintLabel : FlowStepAction::None,
            position: $index,
        ),
        array_keys($keys),
    );
}

it('has every step in front when nothing is completed', function (): void {
    $progress = FulfillmentProgress::of(flowOf(['label', 'packed']), []);

    expect($progress->hasStarted())->toBeFalse()
        ->and($progress->isDone())->toBeFalse()
        ->and($progress->completedCount())->toBe(0)
        ->and($progress->stepCount())->toBe(2)
        ->and($progress->next()?->key)->toBe('label');
});

it('moves the next step along as each one is completed', function (): void {
    $steps = flowOf(['label', 'packed', 'posted']);

    $progress = FulfillmentProgress::of($steps, ['ffs_label']);

    expect($progress->hasStarted())->toBeTrue()
        ->and($progress->completedCount())->toBe(1)
        ->and($progress->next()?->key)->toBe('packed');
});

it('is done once every step is behind it', function (): void {
    $steps = flowOf(['label', 'packed']);

    $progress = FulfillmentProgress::of($steps, ['ffs_label', 'ffs_packed']);

    expect($progress->isDone())->toBeTrue()
        ->and($progress->next())->toBeNull()
        ->and($progress->completedCount())->toBe(2);
});

it('is done and unstarted on a flow with no steps', function (): void {
    $progress = FulfillmentProgress::of([], []);

    expect($progress->isDone())->toBeTrue()
        ->and($progress->hasStarted())->toBeFalse()
        ->and($progress->stepCount())->toBe(0)
        ->and($progress->next())->toBeNull();
});

it('reads the flow as it stands now, ignoring an event naming a step that is gone', function (): void {
    $progress = FulfillmentProgress::of(flowOf(['packed']), ['ffs_label', 'ffs_packed']);

    expect($progress->isDone())->toBeTrue()
        ->and($progress->completedCount())->toBe(1)
        ->and($progress->completed[0]->key)->toBe('packed');
});

it('admits only the step in front', function (): void {
    $progress = FulfillmentProgress::of(flowOf(['label', 'packed']), []);

    expect($progress->admits('ffs_label'))->toBeTrue()
        ->and($progress->admits('ffs_packed'))->toBeFalse()
        ->and($progress->admits('ffs_nothing'))->toBeFalse();
});

it('admits nothing once the flow is done', function (): void {
    $progress = FulfillmentProgress::of(flowOf(['label']), ['ffs_label']);

    expect($progress->admits('ffs_label'))->toBeFalse();
});

it('keeps a completed step out of the remaining list wherever it sits', function (): void {
    $progress = FulfillmentProgress::of(flowOf(['label', 'packed', 'posted']), ['ffs_packed']);

    expect(array_map(fn (FlowStep $step): string => $step->key, $progress->remaining))->toBe(['label', 'posted'])
        ->and($progress->next()?->key)->toBe('label');
});
