<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Fulfillment\FlowStep;
use App\Domain\Fulfillment\FlowStepAction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

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

it('creates the default-flow index as a bare boolean predicate in the schema itself', function (): void {
    $sql = DB::table('sqlite_master')
        ->where('type', 'index')
        ->where('name', 'fulfillment_flows_default_unique')
        ->value('sql');

    if (! is_string($sql)) {
        throw new RuntimeException('sqlite_master carries no such index.');
    }

    expect($sql)->toContain('where is_default')
        ->and($sql)->not->toContain('is_default = 1');
});

it('refuses a second default flow for one seller', function (): void {
    $seller = $this->seller('Molly Weasley');
    FulfillmentFlow::factory()->isDefault()->create(['seller_id' => $seller->id]);

    expect(fn () => FulfillmentFlow::factory()->isDefault()->create(['seller_id' => $seller->id]))
        ->toThrow(QueryException::class);
});

it('lets one seller hold many flows that are not the default', function (): void {
    $seller = $this->seller('Luna Lovegood');
    FulfillmentFlow::factory()->isDefault()->create(['seller_id' => $seller->id]);
    FulfillmentFlow::factory()->create(['seller_id' => $seller->id, 'name' => 'Framed pieces']);
    FulfillmentFlow::factory()->create(['seller_id' => $seller->id, 'name' => 'Made to order']);

    expect(FulfillmentFlow::where('seller_id', $seller->id)->count())->toBe(3);
});

it('lets two sellers each hold their own default flow', function (): void {
    FulfillmentFlow::factory()->isDefault()->create(['seller_id' => $this->seller('Molly Weasley')->id]);
    FulfillmentFlow::factory()->isDefault()->create(['seller_id' => $this->seller('Luna Lovegood')->id]);

    expect(FulfillmentFlow::query()->defaults()->count())->toBe(2);
});

it('lists the listings that name it', function (): void {
    $seller = $this->seller();
    $flow = FulfillmentFlow::factory()->create(['seller_id' => $seller->id]);
    $named = $this->listing($seller, ['fulfillment_flow_id' => $flow->id]);
    $this->listing($seller);

    expect($flow->listings->pluck('id')->all())->toBe([$named->id]);
});

it('carries the flow\'s seller onto a step built without one named', function (): void {
    $flow = FulfillmentFlow::factory()->create(['seller_id' => $this->seller('Neville Longbottom')->id]);

    $step = FulfillmentFlowStep::factory()->create(['fulfillment_flow_id' => $flow->id]);

    expect($step->seller_id)->toBe($flow->seller_id);
});
