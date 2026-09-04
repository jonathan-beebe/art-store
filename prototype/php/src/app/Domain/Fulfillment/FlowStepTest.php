<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment;

it('carries the id, key, words, action, and place of one step', function (): void {
    $step = new FlowStep('ffs_label', 'label-printed', 'Label printed', FlowStepAction::PrintLabel, 2);

    expect($step->id)->toBe('ffs_label')
        ->and($step->key)->toBe('label-printed')
        ->and($step->label)->toBe('Label printed')
        ->and($step->action)->toBe(FlowStepAction::PrintLabel)
        ->and($step->position)->toBe(2);
});

it('says whether completing it prints a label', function (FlowStepAction $action, bool $printsLabel): void {
    $step = new FlowStep('ffs_one', 'one', 'One', $action, 0);

    expect($step->printsLabel())->toBe($printsLabel);
})->with([
    [FlowStepAction::PrintLabel, true],
    [FlowStepAction::None, false],
]);
