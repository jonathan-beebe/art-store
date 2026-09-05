<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment;

use App\Domain\DomainRuleViolation;

it('trims the label and carries the id, label, and action', function (): void {
    $draft = FlowStepDraft::of('ffs_label', '  Label printed  ', FlowStepAction::PrintLabel);

    expect($draft->id)->toBe('ffs_label')
        ->and($draft->label)->toBe('Label printed')
        ->and($draft->action)->toBe(FlowStepAction::PrintLabel);
});

it('is new with no id', function (): void {
    $draft = FlowStepDraft::of(null, 'Packed', FlowStepAction::None);

    expect($draft->isNew())->toBeTrue();
});

it('is not new with an id', function (): void {
    $draft = FlowStepDraft::of('ffs_packed', 'Packed', FlowStepAction::None);

    expect($draft->isNew())->toBeFalse();
});

it('refuses a blank label', function (string $label): void {
    expect(fn () => FlowStepDraft::of(null, $label, FlowStepAction::None))
        ->toThrow(DomainRuleViolation::class);
})->with([
    'empty' => [''],
    'spaces' => ['   '],
]);

it('refuses a label longer than the limit', function (): void {
    $label = str_repeat('a', FlowStepDraft::LABEL_LIMIT + 1);

    expect(fn () => FlowStepDraft::of(null, $label, FlowStepAction::None))
        ->toThrow(DomainRuleViolation::class);
});

it('admits a label exactly at the limit', function (): void {
    $label = str_repeat('a', FlowStepDraft::LABEL_LIMIT);

    $draft = FlowStepDraft::of(null, $label, FlowStepAction::None);

    expect($draft->label)->toBe($label);
});
