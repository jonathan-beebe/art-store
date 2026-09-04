<?php

declare(strict_types=1);

namespace App\Actions\Fulfillment;

use App\Domain\Auth\ActorType;
use App\Domain\DomainRuleViolation;
use App\Domain\Fulfillment\FulfillmentLane;
use App\Models\FulfillmentEvent;
use App\Models\FulfillmentFlow;
use App\Models\FulfillmentFlowStep;

it('completes the first step, appending one step_completed event for the seller', function (): void {
    $seller = $this->seller('Molly Weasley');
    $fulfillment = $this->paidFulfillmentFor($seller);
    [$labelStep] = $this->flowFor($seller);

    $event = app(CompleteFlowStep::class)($fulfillment, $labelStep, 'Owl Post', 'OP 1234', $this->moment('2026-08-21 09:00:00'));

    expect($event->fulfillment_flow_step_id)->toBe($labelStep->id)
        ->and($event->actor_type)->toBe(ActorType::Seller)
        ->and($event->actor_id)->toBe($seller->id)
        ->and($event->carrier)->toBe('Owl Post')
        ->and($event->tracking_number)->toBe('OP 1234')
        ->and(FulfillmentEvent::count())->toBe(1);
});

it('moves the lane from to ship into progress once the first step is behind it, naming the second as next', function (): void {
    $seller = $this->seller('Neville Longbottom');
    $fulfillment = $this->paidFulfillmentFor($seller);
    [$labelStep, $packStep] = $this->flowFor($seller);

    expect($fulfillment->lane())->toBe(FulfillmentLane::ToShip);

    app(CompleteFlowStep::class)($fulfillment, $labelStep, 'Owl Post', 'OP 1234', $this->moment('2026-08-21 09:00:00'));
    $fulfillment->refresh();

    expect($fulfillment->progress()->next()?->id)->toBe($packStep->id)
        ->and($fulfillment->lane())->toBe(FulfillmentLane::InProgress);
});

it('leaves the progress done once the second step is completed', function (): void {
    $seller = $this->seller('Luna Lovegood');
    $fulfillment = $this->paidFulfillmentFor($seller);
    [$labelStep, $packStep] = $this->flowFor($seller);
    $completeStep = app(CompleteFlowStep::class);

    $completeStep($fulfillment, $labelStep, 'Owl Post', 'OP 1234', $this->moment('2026-08-21 09:00:00'));
    $completeStep($fulfillment->refresh(), $packStep, null, null, $this->moment('2026-08-21 10:00:00'));

    expect($fulfillment->refresh()->progress()->isDone())->toBeTrue();
});

it('refuses completing the same step twice, leaving exactly one event', function (): void {
    $seller = $this->seller();
    $fulfillment = $this->paidFulfillmentFor($seller);
    [$labelStep] = $this->flowFor($seller);
    $completeStep = app(CompleteFlowStep::class);

    $completeStep($fulfillment, $labelStep, 'Owl Post', 'OP 1234', $this->moment('2026-08-21 09:00:00'));

    expect(fn () => $completeStep($fulfillment->refresh(), $labelStep, 'Owl Post', 'OP 1234', $this->moment('2026-08-21 10:00:00')))
        ->toThrow(DomainRuleViolation::class);

    expect(FulfillmentEvent::count())->toBe(1);
});

it('refuses a step submitted out of order', function (): void {
    $seller = $this->seller();
    $fulfillment = $this->paidFulfillmentFor($seller);
    [, $packStep] = $this->flowFor($seller);

    expect(fn () => app(CompleteFlowStep::class)($fulfillment, $packStep, null, null, $this->moment('2026-08-21 09:00:00')))
        ->toThrow(DomainRuleViolation::class);

    expect(FulfillmentEvent::count())->toBe(0);
});

it('refuses completing a step on a fulfillment that is not awaiting shipment, appending nothing', function (): void {
    $seller = $this->seller();
    $fulfillment = $this->shippedFulfillmentFor($seller);
    [$labelStep] = $this->flowFor($seller);
    $before = FulfillmentEvent::count();

    expect(fn () => app(CompleteFlowStep::class)($fulfillment, $labelStep, 'Owl Post', 'OP 1234', $this->moment('2026-08-22 09:00:00')))
        ->toThrow(DomainRuleViolation::class);

    expect(FulfillmentEvent::count())->toBe($before);
});

it('refuses a label step with no carrier or no tracking number', function (?string $carrier, ?string $trackingNumber): void {
    $seller = $this->seller();
    $fulfillment = $this->paidFulfillmentFor($seller);
    [$labelStep] = $this->flowFor($seller);

    expect(fn () => app(CompleteFlowStep::class)($fulfillment, $labelStep, $carrier, $trackingNumber, $this->moment('2026-08-21 09:00:00')))
        ->toThrow(DomainRuleViolation::class);
})->with([
    'no carrier' => [null, 'OP 1234'],
    'no tracking number' => ['Owl Post', null],
    'neither' => [null, null],
]);

it('refuses shipment details on a step that prints no label', function (): void {
    $seller = $this->seller();
    $fulfillment = $this->paidFulfillmentFor($seller);
    [$labelStep, $packStep] = $this->flowFor($seller);
    app(CompleteFlowStep::class)($fulfillment, $labelStep, 'Owl Post', 'OP 1234', $this->moment('2026-08-21 09:00:00'));

    expect(fn () => app(CompleteFlowStep::class)($fulfillment->refresh(), $packStep, 'Owl Post', 'OP 1234', $this->moment('2026-08-21 10:00:00')))
        ->toThrow(DomainRuleViolation::class);
});

it('ships by the flow a listing names instead of the seller default', function (): void {
    $seller = $this->seller('Cho Chang');
    $this->flowFor($seller);
    $fulfillment = $this->paidFulfillmentFor($seller);

    $namedFlow = FulfillmentFlow::factory()->create(['seller_id' => $seller->id, 'name' => 'Framed pieces']);
    $namedStep = FulfillmentFlowStep::factory()->of($namedFlow, 0)->create(['label' => 'Wrapped in brown paper']);

    $listing = $fulfillment->load('order.items.listing')->order->items->sole()->listing;
    $listing->update(['fulfillment_flow_id' => $namedFlow->id]);

    expect($fulfillment->refresh()->progress()->next()?->id)->toBe($namedStep->id);
});
