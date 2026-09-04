<?php

declare(strict_types=1);

namespace App\Actions\Fulfillment;

use App\Domain\Fulfillment\FlowStepAction;
use App\Domain\Fulfillment\FlowStepDraft;
use App\Models\FulfillmentFlow;

it('makes a sellers first flow the default and writes its steps', function (): void {
    $seller = $this->seller('Molly Weasley');
    $drafts = [FlowStepDraft::of(null, 'Label printed', FlowStepAction::PrintLabel)];

    $flow = app(CreateFulfillmentFlow::class)($seller, 'How I ship', $drafts);

    expect($flow->is_default)->toBeTrue()
        ->and($flow->name)->toBe('How I ship')
        ->and($flow->seller_id)->toBe($seller->id)
        ->and($flow->steps()->pluck('label')->all())->toBe(['Label printed']);
});

it('leaves a sellers second flow off the default role', function (): void {
    $seller = $this->seller('Neville Longbottom');
    $create = app(CreateFulfillmentFlow::class);
    $first = $create($seller, 'How I ship', []);

    $second = $create($seller, 'Framed pieces', []);

    expect($first->refresh()->is_default)->toBeTrue()
        ->and($second->is_default)->toBeFalse()
        ->and(FulfillmentFlow::where('seller_id', $seller->id)->count())->toBe(2);
});
