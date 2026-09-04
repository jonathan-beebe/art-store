<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment;

it('says which action prints a label', function (): void {
    expect(FlowStepAction::PrintLabel->printsLabel())->toBeTrue()
        ->and(FlowStepAction::None->printsLabel())->toBeFalse();
});

it('names each action and the control that runs it', function (FlowStepAction $action, string $label, string $control): void {
    expect($action->label())->toBe($label)
        ->and($action->control())->toBe($control);
})->with([
    [FlowStepAction::None, 'Record it only', 'Mark done'],
    [FlowStepAction::PrintLabel, 'Print a shipping label', 'Print label'],
]);

it('carries the action of the step it belongs to', function (): void {
    $step = new FlowStep('ffs_label', 'label', 'Label printed', FlowStepAction::PrintLabel, 0);

    expect($step->printsLabel())->toBeTrue();
});
