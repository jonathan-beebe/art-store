<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Fulfillment\FlowStep;
use App\Domain\Fulfillment\FlowStepAction;

it('mints a prefixed id', function (): void {
    expect(FulfillmentFlow::factory()->create()->id)->toStartWith('ffl_');
});

it('lists its steps in position order regardless of insertion order', function (): void {
    $flow = FulfillmentFlow::factory()->create();
    $second = FulfillmentFlowStep::factory()->of($flow, 1)->create(['key' => 'shipped', 'label' => 'Shipped']);
    $first = FulfillmentFlowStep::factory()->of($flow, 0)->create(['key' => 'packed', 'label' => 'Packed']);
    $flow->load('steps');

    expect($flow->steps->pluck('id')->all())->toBe([$first->id, $second->id]);
});

it('maps its steps to flow-step value objects in position order', function (): void {
    $flow = FulfillmentFlow::factory()->create();
    $labelStep = FulfillmentFlowStep::factory()->of($flow, 1)->printsLabel()->create();
    $packedStep = FulfillmentFlowStep::factory()->of($flow, 0)->create(['key' => 'packed', 'label' => 'Packed']);
    $flow->load('steps');

    expect($flow->flowSteps())->toEqual([
        new FlowStep($packedStep->id, 'packed', 'Packed', FlowStepAction::None, 0),
        new FlowStep($labelStep->id, 'label-printed', 'Label printed', FlowStepAction::PrintLabel, 1),
    ]);
});

it('scopes to only the default flows', function (): void {
    $seller = $this->seller();
    $default = FulfillmentFlow::factory()->isDefault()->create(['seller_id' => $seller->id]);
    FulfillmentFlow::factory()->create(['seller_id' => $seller->id]);

    expect(FulfillmentFlow::query()->defaults()->pluck('id')->all())->toBe([$default->id]);
});

it('resolves its seller', function (): void {
    $seller = $this->seller();
    $flow = FulfillmentFlow::factory()->create(['seller_id' => $seller->id]);

    expect($flow->load('seller')->seller->is($seller))->toBeTrue();
});
