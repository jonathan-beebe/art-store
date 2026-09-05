<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Fulfillment\FlowStep;
use App\Domain\Fulfillment\FlowStepAction;
use Illuminate\Database\QueryException;

it('mints a prefixed id', function (): void {
    expect(FulfillmentFlowStep::factory()->create()->id)->toStartWith('ffs_');
});

it('casts its action to a FlowStepAction', function (): void {
    $step = FulfillmentFlowStep::factory()->printsLabel()->create();

    expect($step->action)->toBe(FlowStepAction::PrintLabel);
});

it('turns itself into a flow-step value object carrying every field', function (): void {
    $step = FulfillmentFlowStep::factory()->printsLabel()->create(['position' => 2]);

    expect($step->toFlowStep())->toEqual(new FlowStep($step->id, $step->key, $step->label, $step->action, $step->position));
});

it('resolves its flow and seller relations', function (): void {
    $seller = $this->seller();
    $flow = FulfillmentFlow::factory()->create(['seller_id' => $seller->id]);
    $step = FulfillmentFlowStep::factory()->of($flow, 0)->create();
    $step->load(['fulfillmentFlow', 'seller']);

    expect($step->fulfillmentFlow->is($flow))->toBeTrue()
        ->and($step->seller->is($seller))->toBeTrue();
});

it('rejects a second step with the same key in one flow', function (): void {
    $flow = FulfillmentFlow::factory()->create();
    FulfillmentFlowStep::factory()->of($flow, 0)->create(['key' => 'packed']);

    expect(fn () => FulfillmentFlowStep::factory()->of($flow, 1)->create(['key' => 'packed']))
        ->toThrow(QueryException::class);
});

it('rejects a second step at the same position in one flow', function (): void {
    $flow = FulfillmentFlow::factory()->create();
    FulfillmentFlowStep::factory()->of($flow, 0)->create(['key' => 'packed']);

    expect(fn () => FulfillmentFlowStep::factory()->of($flow, 0)->create(['key' => 'label-printed']))
        ->toThrow(QueryException::class);
});

it('permits two different flows to each hold a step with the same key', function (): void {
    $first = FulfillmentFlowStep::factory()->of(FulfillmentFlow::factory()->create(), 0)->create();
    $second = FulfillmentFlowStep::factory()->of(FulfillmentFlow::factory()->create(), 0)->create();

    expect($first->key)->toBe($second->key)
        ->and($first->id)->not->toBe($second->id);
});
