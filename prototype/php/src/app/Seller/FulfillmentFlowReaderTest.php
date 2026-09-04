<?php

declare(strict_types=1);

namespace App\Seller;

use App\Actions\Fulfillment\CompleteFlowStep;
use App\Actions\Orders\FinalizeOrder;
use App\Domain\Fulfillment\FlowStep;
use App\Domain\Fulfillment\FulfillmentEventKind;
use App\Domain\Fulfillment\FulfillmentLane;
use App\Models\Fulfillment;
use App\Models\FulfillmentEvent;
use App\Models\FulfillmentFlow;
use App\Models\FulfillmentFlowStep;

/**
 * Loads what {@see FulfillmentFlowReader} reads, the way the page and the
 * action that call it do.
 */
function loadedForFlow(Fulfillment $fulfillment): Fulfillment
{
    return $fulfillment->load([
        'order.items.listing.fulfillmentFlow.steps',
        'seller.defaultFulfillmentFlow.steps',
        'fulfillmentEvents',
    ]);
}

it('ships by nothing at all when its seller has no flow', function (): void {
    $fulfillment = loadedForFlow($this->paidFulfillmentFor($this->seller()));
    $reader = app(FulfillmentFlowReader::class);

    expect($reader->flowInEffect($fulfillment))->toBeNull()
        ->and($reader->flowSteps($fulfillment))->toBe([])
        ->and($reader->progress($fulfillment)->isDone())->toBeTrue()
        ->and($fulfillment->lane($reader->progress($fulfillment)))->toBe(FulfillmentLane::ToShip);
});

it('ships by its seller default flow when no listing on it names one', function (): void {
    $seller = $this->seller('Molly Weasley');
    $flow = FulfillmentFlow::factory()->isDefault()->create(['seller_id' => $seller->id, 'name' => 'How I ship']);
    FulfillmentFlowStep::factory()->of($flow, 0)->create(['label' => 'Packed']);
    $fulfillment = loadedForFlow($this->paidFulfillmentFor($seller));
    $reader = app(FulfillmentFlowReader::class);

    expect($reader->flowInEffect($fulfillment)?->id)->toBe($flow->id)
        ->and(array_map(fn (FlowStep $step): string => $step->label, $reader->flowSteps($fulfillment)))->toBe(['Packed']);
});

it('ships by the flow of its first item, in the order Order::items() reads', function (): void {
    $seller = $this->seller('Molly Weasley');
    $earlyFlow = FulfillmentFlow::factory()->create(['seller_id' => $seller->id]);
    $lateFlow = FulfillmentFlow::factory()->create(['seller_id' => $seller->id]);
    $earlyListing = $this->listing($seller, ['fulfillment_flow_id' => $earlyFlow->id]);
    $lateListing = $this->listing($seller, ['fulfillment_flow_id' => $lateFlow->id]);

    // Added late listing first, so an unordered read would land on it.
    $order = $this->orderFor($this->verifiedCustomer(), $lateListing, $earlyListing);
    app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));
    $fulfillment = $order->fulfillments()->sole();

    $order->items()->where('listing_id', $earlyListing->id)->update(['created_at' => $this->moment('2026-08-20 08:00:00')]);
    $order->items()->where('listing_id', $lateListing->id)->update(['created_at' => $this->moment('2026-08-20 09:00:00')]);

    $fulfillment = loadedForFlow($fulfillment->refresh());

    expect(app(FulfillmentFlowReader::class)->flowInEffect($fulfillment)?->id)->toBe($earlyFlow->id);
});

it('reads only step completions as progress, leaving the transition events out', function (): void {
    $seller = $this->seller('Luna Lovegood');
    $flow = FulfillmentFlow::factory()->isDefault()->create(['seller_id' => $seller->id]);
    $step = FulfillmentFlowStep::factory()->of($flow, 0)->create(['label' => 'Packed', 'key' => 'packed']);
    $fulfillment = $this->paidFulfillmentFor($seller);
    $reader = app(FulfillmentFlowReader::class);

    FulfillmentEvent::factory()->on($fulfillment)->create(['kind' => FulfillmentEventKind::Shipped]);

    expect($reader->progress(loadedForFlow($fulfillment->refresh()))->hasStarted())->toBeFalse();

    FulfillmentEvent::factory()->on($fulfillment)->completing($step)->create();

    expect($reader->progress(loadedForFlow($fulfillment->refresh()))->hasStarted())->toBeTrue();
});

it('keeps a parcel in progress after the seller removes the step they had completed', function (): void {
    $seller = $this->seller('Molly Weasley');
    $flow = FulfillmentFlow::factory()->isDefault()->create(['seller_id' => $seller->id]);
    $labelStep = FulfillmentFlowStep::factory()->printsLabel()->of($flow, 0)->create();
    FulfillmentFlowStep::factory()->of($flow, 1)->create(['label' => 'Packed', 'key' => 'packed']);
    $fulfillment = $this->paidFulfillmentFor($seller);
    $reader = app(FulfillmentFlowReader::class);

    app(CompleteFlowStep::class)($fulfillment, $labelStep, 'Owl Post', 'OP 4471', $this->moment('2026-08-21 09:00:00'));

    $fulfillment = loadedForFlow($fulfillment->refresh());
    expect($fulfillment->lane($reader->progress($fulfillment)))->toBe(FulfillmentLane::InProgress);

    $labelStep->delete();
    $fulfillment = loadedForFlow($fulfillment->refresh());
    $progress = $reader->progress($fulfillment);

    expect($progress->hasStarted())->toBeTrue()
        ->and($fulfillment->lane($progress))->toBe(FulfillmentLane::InProgress)
        ->and($progress->completed)->toBe([])
        ->and($progress->next()?->label)->toBe('Packed');
});
