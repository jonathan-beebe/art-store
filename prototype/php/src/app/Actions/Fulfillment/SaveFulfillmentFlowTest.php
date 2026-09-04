<?php

declare(strict_types=1);

namespace App\Actions\Fulfillment;

use App\Domain\Fulfillment\FlowStepAction;
use App\Domain\Fulfillment\FlowStepDraft;
use App\Models\FulfillmentFlow;
use App\Models\FulfillmentFlowStep;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

it('creates a default flow named and ordered as submitted, for a seller with none', function (): void {
    $seller = $this->seller('Molly Weasley');
    $drafts = [
        FlowStepDraft::of(null, 'Label printed', FlowStepAction::PrintLabel),
        FlowStepDraft::of(null, 'Packed', FlowStepAction::None),
        FlowStepDraft::of(null, 'Dropped at the post office', FlowStepAction::None),
    ];

    $flow = app(SaveFulfillmentFlow::class)($seller, 'How I ship', $drafts);

    expect($flow->is_default)->toBeTrue()
        ->and($flow->name)->toBe('How I ship')
        ->and($flow->seller_id)->toBe($seller->id);

    $steps = $flow->steps()->orderBy('position')->get();

    expect($steps->pluck('label')->all())->toBe(['Label printed', 'Packed', 'Dropped at the post office'])
        ->and($steps->pluck('position')->all())->toBe([0, 1, 2]);
});

it('reuses the one default flow across two calls for the same seller', function (): void {
    $seller = $this->seller('Neville Longbottom');
    $save = app(SaveFulfillmentFlow::class);

    $first = $save($seller, 'How I ship', [FlowStepDraft::of(null, 'Packed', FlowStepAction::None)]);
    $second = $save($seller, 'How I ship', [FlowStepDraft::of(null, 'Packed', FlowStepAction::None)]);

    expect($second->id)->toBe($first->id)
        ->and(FulfillmentFlow::where('seller_id', $seller->id)->count())->toBe(1);
});

it('keeps a step\'s row and key through a rename, updating its label and action', function (): void {
    $seller = $this->seller('Luna Lovegood');
    $save = app(SaveFulfillmentFlow::class);
    $flow = $save($seller, 'How I ship', [FlowStepDraft::of(null, 'Packed', FlowStepAction::None)]);
    $step = $flow->steps()->sole();

    $renamed = $save($seller, 'How I ship', [FlowStepDraft::of($step->id, 'Boxed up', FlowStepAction::PrintLabel)]);
    $reloaded = $renamed->steps()->sole();

    expect($reloaded->id)->toBe($step->id)
        ->and($reloaded->key)->toBe($step->key)
        ->and($reloaded->label)->toBe('Boxed up')
        ->and($reloaded->action)->toBe(FlowStepAction::PrintLabel);
});

it('deletes a step left out of the drafts', function (): void {
    $seller = $this->seller();
    $save = app(SaveFulfillmentFlow::class);
    $flow = $save($seller, 'How I ship', [
        FlowStepDraft::of(null, 'Label printed', FlowStepAction::PrintLabel),
        FlowStepDraft::of(null, 'Packed', FlowStepAction::None),
    ]);
    $labelStep = $flow->steps()->where('key', 'label-printed')->sole();
    $packStep = $flow->steps()->where('key', 'packed')->sole();

    $flow = $save($seller, 'How I ship', [
        FlowStepDraft::of($labelStep->id, 'Label printed', FlowStepAction::PrintLabel),
    ]);

    expect($flow->steps()->count())->toBe(1)
        ->and(FulfillmentFlowStep::find($packStep->id))->toBeNull();
});

it('reorders two existing steps without a unique-index collision', function (): void {
    $seller = $this->seller();
    $save = app(SaveFulfillmentFlow::class);
    $flow = $save($seller, 'How I ship', [
        FlowStepDraft::of(null, 'Label printed', FlowStepAction::PrintLabel),
        FlowStepDraft::of(null, 'Packed', FlowStepAction::None),
    ]);
    $first = $flow->steps()->where('label', 'Label printed')->sole();
    $second = $flow->steps()->where('label', 'Packed')->sole();

    $flow = $save($seller, 'How I ship', [
        FlowStepDraft::of($second->id, $second->label, $second->action),
        FlowStepDraft::of($first->id, $first->label, $first->action),
    ]);

    $reordered = $flow->steps()->orderBy('position')->get();

    expect($reordered->pluck('id')->all())->toBe([$second->id, $first->id])
        ->and($reordered->pluck('position')->all())->toBe([0, 1]);
});

it('parks a step above the range, never below zero, while it reorders', function (): void {
    $seller = $this->seller();
    $save = app(SaveFulfillmentFlow::class);
    $flow = $save($seller, 'How I ship', [
        FlowStepDraft::of(null, 'Label printed', FlowStepAction::PrintLabel),
        FlowStepDraft::of(null, 'Packed', FlowStepAction::None),
    ]);
    $first = $flow->steps()->where('label', 'Label printed')->sole();
    $second = $flow->steps()->where('label', 'Packed')->sole();

    $positions = [];
    DB::listen(function (QueryExecuted $query) use (&$positions): void {
        if (str_contains($query->sql, 'update "fulfillment_flow_steps"')) {
            foreach ($query->bindings as $binding) {
                if (is_int($binding)) {
                    $positions[] = $binding;
                }
            }
        }
    });

    $save($seller, 'How I ship', [
        FlowStepDraft::of($second->id, $second->label, $second->action),
        FlowStepDraft::of($first->id, $first->label, $first->action),
    ]);

    expect($positions)->not->toBeEmpty();

    foreach ($positions as $position) {
        expect($position)->toBeGreaterThanOrEqual(0);
    }
});

it('slugs a new step\'s key from its label, numbering a second step whose label slugs the same', function (): void {
    $seller = $this->seller();

    $flow = app(SaveFulfillmentFlow::class)($seller, 'How I ship', [
        FlowStepDraft::of(null, 'Packed!', FlowStepAction::None),
        FlowStepDraft::of(null, 'Packed?', FlowStepAction::None),
    ]);

    $keys = $flow->steps()->orderBy('position')->pluck('key')->all();

    expect($keys)->toBe(['packed', 'packed-2']);
});

it('leaves the flow with no steps when the drafts are empty', function (): void {
    $seller = $this->seller();
    $save = app(SaveFulfillmentFlow::class);
    $save($seller, 'How I ship', [FlowStepDraft::of(null, 'Packed', FlowStepAction::None)]);

    $flow = $save($seller, 'How I ship', []);

    expect($flow->steps()->count())->toBe(0);
});

it('updates the name on an existing flow', function (): void {
    $seller = $this->seller();
    $save = app(SaveFulfillmentFlow::class);
    $save($seller, 'How I ship', []);

    $flow = $save($seller, 'How Molly ships', []);

    expect($flow->name)->toBe('How Molly ships');
});
