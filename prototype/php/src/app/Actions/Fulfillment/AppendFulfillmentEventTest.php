<?php

declare(strict_types=1);

namespace App\Actions\Fulfillment;

use App\Domain\Auth\ActorType;
use App\Domain\Fulfillment\FulfillmentEventKind;
use App\Domain\Fulfillment\NewFulfillmentEvent;
use App\Models\FulfillmentFlow;
use App\Models\FulfillmentFlowStep;

it('appends a transition event naming no step and no shipment details', function (): void {
    $seller = $this->seller('Molly Weasley');
    $fulfillment = $this->paidFulfillmentFor($seller);
    $now = $this->moment('2026-08-21 09:00:00');

    $event = app(AppendFulfillmentEvent::class)($fulfillment, NewFulfillmentEvent::transition(
        FulfillmentEventKind::Shipped,
        ActorType::Seller,
        $seller->id,
        $now,
    ));

    expect($event->fulfillment_id)->toBe($fulfillment->id)
        ->and($event->seller_id)->toBe($seller->id)
        ->and($event->kind)->toBe(FulfillmentEventKind::Shipped)
        ->and($event->actor_type)->toBe(ActorType::Seller)
        ->and($event->actor_id)->toBe($seller->id)
        ->and($event->occurred_at->format('Y-m-d H:i:s'))->toBe('2026-08-21 09:00:00')
        ->and($event->fulfillment_flow_step_id)->toBeNull()
        ->and($event->step_label)->toBeNull()
        ->and($event->carrier)->toBeNull()
        ->and($event->tracking_number)->toBeNull();
});

it('appends a step completion carrying the step id, a copy of its label, and the shipment details', function (): void {
    $seller = $this->seller('Neville Longbottom');
    $fulfillment = $this->paidFulfillmentFor($seller);
    $flow = FulfillmentFlow::factory()->isDefault()->create(['seller_id' => $seller->id]);
    $step = FulfillmentFlowStep::factory()->printsLabel()->of($flow, 0)->create();
    $now = $this->moment('2026-08-21 09:00:00');

    $event = app(AppendFulfillmentEvent::class)($fulfillment, NewFulfillmentEvent::stepCompleted(
        $step->toFlowStep(),
        ActorType::Seller,
        $seller->id,
        $now,
        'Owl Post',
        'OP 1234',
    ));

    expect($event->kind)->toBe(FulfillmentEventKind::StepCompleted)
        ->and($event->fulfillment_flow_step_id)->toBe($step->id)
        ->and($event->step_label)->toBe($step->label)
        ->and($event->carrier)->toBe('Owl Post')
        ->and($event->tracking_number)->toBe('OP 1234');
});

it('keeps the copied step label after the step is deleted, and clears the foreign key', function (): void {
    $seller = $this->seller('Luna Lovegood');
    $fulfillment = $this->paidFulfillmentFor($seller);
    $flow = FulfillmentFlow::factory()->isDefault()->create(['seller_id' => $seller->id]);
    $step = FulfillmentFlowStep::factory()->of($flow, 0)->create(['label' => 'Packed']);
    $now = $this->moment('2026-08-21 09:00:00');

    $event = app(AppendFulfillmentEvent::class)($fulfillment, NewFulfillmentEvent::stepCompleted(
        $step->toFlowStep(),
        ActorType::Seller,
        $seller->id,
        $now,
    ));

    $step->delete();
    $event->refresh();

    expect($event->fulfillment_flow_step_id)->toBeNull()
        ->and($event->step_label)->toBe('Packed');
});
