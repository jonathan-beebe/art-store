<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment;

it('starts every seller with printing a label then packing', function (): void {
    $drafts = DefaultFlow::drafts();

    expect($drafts)->toHaveCount(2)
        ->and($drafts[0]->id)->toBeNull()
        ->and($drafts[0]->label)->toBe('Label printed')
        ->and($drafts[0]->action)->toBe(FlowStepAction::PrintLabel)
        ->and($drafts[1]->id)->toBeNull()
        ->and($drafts[1]->label)->toBe('Packed')
        ->and($drafts[1]->action)->toBe(FlowStepAction::None);
});

it('names the default flow', function (): void {
    expect(DefaultFlow::NAME)->toBe('How I ship');
});
