<?php

declare(strict_types=1);

namespace App\Actions\Fulfillment;

use App\Domain\Fulfillment\FlowStepAction;
use App\Domain\Fulfillment\FlowStepDraft;
use App\Models\FulfillmentFlow;
use App\Models\FulfillmentFlowStep;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

it('renames a flow and writes its steps named and ordered as submitted', function (): void {
    $flow = FulfillmentFlow::factory()->create(['seller_id' => $this->seller('Molly Weasley')->id, 'name' => 'Draft']);
    $drafts = [
        FlowStepDraft::of(null, 'Label printed', FlowStepAction::PrintLabel),
        FlowStepDraft::of(null, 'Packed', FlowStepAction::None),
        FlowStepDraft::of(null, 'Dropped at the post office', FlowStepAction::None),
    ];

    $saved = app(SaveFulfillmentFlow::class)($flow, 'How I ship', $drafts);

    expect($saved->id)->toBe($flow->id)
        ->and($saved->name)->toBe('How I ship');

    $steps = $saved->steps()->orderBy('position')->get();

    expect($steps->pluck('label')->all())->toBe(['Label printed', 'Packed', 'Dropped at the post office'])
        ->and($steps->pluck('position')->all())->toBe([0, 1, 2]);
});

it('keeps a step\'s row and key through a rename, updating its label and action', function (): void {
    $flow = FulfillmentFlow::factory()->create(['seller_id' => $this->seller('Luna Lovegood')->id]);
    $save = app(SaveFulfillmentFlow::class);
    $save($flow, 'How I ship', [FlowStepDraft::of(null, 'Packed', FlowStepAction::None)]);
    $step = $flow->steps()->sole();

    $renamed = $save($flow, 'How I ship', [FlowStepDraft::of($step->id, 'Boxed up', FlowStepAction::PrintLabel)]);
    $reloaded = $renamed->steps()->sole();

    expect($reloaded->id)->toBe($step->id)
        ->and($reloaded->key)->toBe($step->key)
        ->and($reloaded->label)->toBe('Boxed up')
        ->and($reloaded->action)->toBe(FlowStepAction::PrintLabel);
});

it('deletes a step left out of the drafts', function (): void {
    $flow = FulfillmentFlow::factory()->create(['seller_id' => $this->seller()->id]);
    $save = app(SaveFulfillmentFlow::class);
    $save($flow, 'How I ship', [
        FlowStepDraft::of(null, 'Label printed', FlowStepAction::PrintLabel),
        FlowStepDraft::of(null, 'Packed', FlowStepAction::None),
    ]);
    $labelStep = $flow->steps()->where('key', 'label-printed')->sole();
    $packStep = $flow->steps()->where('key', 'packed')->sole();

    $saved = $save($flow, 'How I ship', [
        FlowStepDraft::of($labelStep->id, 'Label printed', FlowStepAction::PrintLabel),
    ]);

    expect($saved->steps()->count())->toBe(1)
        ->and(FulfillmentFlowStep::find($packStep->id))->toBeNull();
});

it('reorders two existing steps without a unique-index collision', function (): void {
    $flow = FulfillmentFlow::factory()->create(['seller_id' => $this->seller()->id]);
    $save = app(SaveFulfillmentFlow::class);
    $save($flow, 'How I ship', [
        FlowStepDraft::of(null, 'Label printed', FlowStepAction::PrintLabel),
        FlowStepDraft::of(null, 'Packed', FlowStepAction::None),
    ]);
    $first = $flow->steps()->where('label', 'Label printed')->sole();
    $second = $flow->steps()->where('label', 'Packed')->sole();

    $saved = $save($flow, 'How I ship', [
        FlowStepDraft::of($second->id, $second->label, $second->action),
        FlowStepDraft::of($first->id, $first->label, $first->action),
    ]);

    $reordered = $saved->steps()->orderBy('position')->get();

    expect($reordered->pluck('id')->all())->toBe([$second->id, $first->id])
        ->and($reordered->pluck('position')->all())->toBe([0, 1]);
});

it('parks a step at or above 9999 while it reorders, then writes the final range from zero', function (): void {
    $flow = FulfillmentFlow::factory()->create(['seller_id' => $this->seller()->id]);
    $save = app(SaveFulfillmentFlow::class);
    $save($flow, 'How I ship', [
        FlowStepDraft::of(null, 'Label printed', FlowStepAction::PrintLabel),
        FlowStepDraft::of(null, 'Packed', FlowStepAction::None),
    ]);
    $first = $flow->steps()->where('label', 'Label printed')->sole();
    $second = $flow->steps()->where('label', 'Packed')->sole();

    $positions = [];
    DB::listen(function (QueryExecuted $query) use (&$positions): void {
        if (str_contains($query->sql, 'update "fulfillment_flow_steps" set "position"')) {
            foreach ($query->bindings as $binding) {
                if (is_int($binding)) {
                    $positions[] = $binding;
                }
            }
        }
    });

    $save($flow, 'How I ship', [
        FlowStepDraft::of($second->id, $second->label, $second->action),
        FlowStepDraft::of($first->id, $first->label, $first->action),
    ]);

    // Two surviving steps: parkPositions() writes the first two bindings
    // (the park, above the range), writeSteps() the last two (the final
    // 0-based range).
    expect($positions)->toHaveCount(4)
        ->and(array_slice($positions, 0, 2))->each->toBeGreaterThanOrEqual(9999)
        ->and(array_slice($positions, 2, 2))->toBe([0, 1]);
});

it('slugs a new step\'s key from its label, numbering a second step whose label slugs the same', function (): void {
    $flow = FulfillmentFlow::factory()->create(['seller_id' => $this->seller()->id]);

    $saved = app(SaveFulfillmentFlow::class)($flow, 'How I ship', [
        FlowStepDraft::of(null, 'Packed!', FlowStepAction::None),
        FlowStepDraft::of(null, 'Packed?', FlowStepAction::None),
    ]);

    $keys = $saved->steps()->orderBy('position')->pluck('key')->all();

    expect($keys)->toBe(['packed', 'packed-2']);
});

it('leaves the flow with no steps when the drafts are empty', function (): void {
    $flow = FulfillmentFlow::factory()->create(['seller_id' => $this->seller()->id]);
    $save = app(SaveFulfillmentFlow::class);
    $save($flow, 'How I ship', [FlowStepDraft::of(null, 'Packed', FlowStepAction::None)]);

    $saved = $save($flow, 'How I ship', []);

    expect($saved->steps()->count())->toBe(0);
});

it('updates the name on an existing flow', function (): void {
    $flow = FulfillmentFlow::factory()->create(['seller_id' => $this->seller()->id, 'name' => 'How I ship']);
    $save = app(SaveFulfillmentFlow::class);
    $save($flow, 'How I ship', []);

    $saved = $save($flow, 'How Molly ships', []);

    expect($saved->name)->toBe('How Molly ships');
});
