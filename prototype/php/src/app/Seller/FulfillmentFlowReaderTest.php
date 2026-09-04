<?php

declare(strict_types=1);

namespace App\Seller;

use App\Actions\Fulfillment\CompleteFlowStep;
use App\Actions\Orders\FinalizeOrder;
use App\Domain\Fulfillment\FlowStep;
use App\Domain\Fulfillment\FulfillmentEventKind;
use App\Domain\Fulfillment\FulfillmentLane;
use App\Models\FulfillmentEvent;
use App\Models\FulfillmentFlow;
use App\Models\FulfillmentFlowStep;

it('ships by nothing at all when its seller has no flow', function (): void {
    $fulfillment = $this->loadedForFlow($this->paidFulfillmentFor($this->seller()));
    $facts = app(FulfillmentFlowReader::class)->read($fulfillment);

    expect($facts->flow)->toBeNull()
        ->and($facts->steps)->toBe([])
        ->and($facts->progress->isDone())->toBeTrue()
        ->and($fulfillment->lane($facts->progress))->toBe(FulfillmentLane::ToShip);
});

it('ships by its seller default flow when no listing on it names one', function (): void {
    $seller = $this->seller('Molly Weasley');
    $flow = FulfillmentFlow::factory()->isDefault()->create(['seller_id' => $seller->id, 'name' => 'How I ship']);
    FulfillmentFlowStep::factory()->of($flow, 0)->create(['label' => 'Packed']);
    $fulfillment = $this->loadedForFlow($this->paidFulfillmentFor($seller));
    $facts = app(FulfillmentFlowReader::class)->read($fulfillment);

    expect($facts->flow?->id)->toBe($flow->id)
        ->and(array_map(fn (FlowStep $step): string => $step->label, $facts->steps))->toBe(['Packed']);
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

    $fulfillment = $this->loadedForFlow($fulfillment->refresh());

    expect(app(FulfillmentFlowReader::class)->read($fulfillment)->flow?->id)->toBe($earlyFlow->id);
});

it('reads only step completions as progress, leaving the transition events out', function (): void {
    $seller = $this->seller('Luna Lovegood');
    $flow = FulfillmentFlow::factory()->isDefault()->create(['seller_id' => $seller->id]);
    $step = FulfillmentFlowStep::factory()->of($flow, 0)->create(['label' => 'Packed', 'key' => 'packed']);
    $fulfillment = $this->paidFulfillmentFor($seller);
    $reader = app(FulfillmentFlowReader::class);

    FulfillmentEvent::factory()->on($fulfillment)->create(['kind' => FulfillmentEventKind::Shipped]);

    expect($reader->read($this->loadedForFlow($fulfillment->refresh()))->progress->hasStarted())->toBeFalse();

    FulfillmentEvent::factory()->on($fulfillment)->completing($step)->create();

    expect($reader->read($this->loadedForFlow($fulfillment->refresh()))->progress->hasStarted())->toBeTrue();
});

it('keeps a parcel in progress after the seller removes the step they had completed', function (): void {
    $seller = $this->seller('Molly Weasley');
    $flow = FulfillmentFlow::factory()->isDefault()->create(['seller_id' => $seller->id]);
    $labelStep = FulfillmentFlowStep::factory()->printsLabel()->of($flow, 0)->create();
    FulfillmentFlowStep::factory()->of($flow, 1)->create(['label' => 'Packed', 'key' => 'packed']);
    $fulfillment = $this->paidFulfillmentFor($seller);
    $reader = app(FulfillmentFlowReader::class);

    app(CompleteFlowStep::class)($fulfillment, $labelStep, 'Owl Post', 'OP 4471', $this->moment('2026-08-21 09:00:00'));

    $fulfillment = $this->loadedForFlow($fulfillment->refresh());
    expect($fulfillment->lane($reader->read($fulfillment)->progress))->toBe(FulfillmentLane::InProgress);

    $labelStep->delete();
    $fulfillment = $this->loadedForFlow($fulfillment->refresh());
    $progress = $reader->read($fulfillment)->progress;

    expect($progress->hasStarted())->toBeTrue()
        ->and($fulfillment->lane($progress))->toBe(FulfillmentLane::InProgress)
        ->and($progress->completed)->toBe([])
        ->and($progress->next()?->label)->toBe('Packed');
});
