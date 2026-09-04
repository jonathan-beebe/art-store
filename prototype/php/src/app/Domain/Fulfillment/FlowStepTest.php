<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment;

it('says whether completing it prints a label', function (FlowStepAction $action, bool $printsLabel): void {
    $step = new FlowStep('ffs_one', 'one', 'One', $action, 0);

    expect($step->printsLabel())->toBe($printsLabel);
})->with([
    [FlowStepAction::PrintLabel, true],
    [FlowStepAction::None, false],
]);
