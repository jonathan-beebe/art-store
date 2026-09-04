<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment;

use App\Domain\Auth\ActorType;
use App\Domain\DomainRuleViolation;
use DateTimeImmutable;

function flowMoment(): DateTimeImmutable
{
    return new DateTimeImmutable('2026-09-03T14:30:00+00:00');
}

function labelStep(): FlowStep
{
    return new FlowStep('ffs_label', 'label-printed', 'Label printed', FlowStepAction::PrintLabel, 0);
}

function packingStep(): FlowStep
{
    return new FlowStep('ffs_packed', 'packed', 'Packed', FlowStepAction::None, 1);
}

it('records a transition against the actor who made it', function (): void {
    $event = NewFulfillmentEvent::transition(FulfillmentEventKind::Shipped, ActorType::Seller, 'sel_MOLLY', flowMoment());

    expect($event->kind)->toBe(FulfillmentEventKind::Shipped)
        ->and($event->actorType)->toBe(ActorType::Seller)
        ->and($event->actorId)->toBe('sel_MOLLY')
        ->and($event->occurredAt)->toEqual(flowMoment())
        ->and($event->step)->toBeNull()
        ->and($event->carrier)->toBeNull()
        ->and($event->trackingNumber)->toBeNull();
});

it('refuses a transition that names a step', function (): void {
    expect(fn () => NewFulfillmentEvent::transition(FulfillmentEventKind::StepCompleted, ActorType::Seller, 'sel_MOLLY', flowMoment()))
        ->toThrow(DomainRuleViolation::class);
});

it('records a step completion against the step it names', function (): void {
    $event = NewFulfillmentEvent::stepCompleted(packingStep(), ActorType::Seller, 'sel_MOLLY', flowMoment());

    expect($event->kind)->toBe(FulfillmentEventKind::StepCompleted)
        ->and($event->step?->key)->toBe('packed')
        ->and($event->carrier)->toBeNull()
        ->and($event->trackingNumber)->toBeNull();
});

it('records the carrier and tracking number a label step printed with', function (): void {
    $event = NewFulfillmentEvent::stepCompleted(labelStep(), ActorType::Seller, 'sel_MOLLY', flowMoment(), 'Owl Post', 'OP 4471 2290 88 GB');

    expect($event->carrier)->toBe('Owl Post')
        ->and($event->trackingNumber)->toBe('OP 4471 2290 88 GB');
});

it('refuses a label step with no shipment to print', function (?string $carrier, ?string $trackingNumber): void {
    expect(fn () => NewFulfillmentEvent::stepCompleted(labelStep(), ActorType::Seller, 'sel_MOLLY', flowMoment(), $carrier, $trackingNumber))
        ->toThrow(DomainRuleViolation::class);
})->with([
    'neither' => [null, null],
    'no carrier' => [null, 'OP 4471 2290 88 GB'],
    'no tracking number' => ['Owl Post', null],
    'blank carrier' => ['   ', 'OP 4471 2290 88 GB'],
    'blank tracking number' => ['Owl Post', ' '],
]);

it('refuses shipment details on a step that prints no label', function (?string $carrier, ?string $trackingNumber): void {
    expect(fn () => NewFulfillmentEvent::stepCompleted(packingStep(), ActorType::Seller, 'sel_MOLLY', flowMoment(), $carrier, $trackingNumber))
        ->toThrow(DomainRuleViolation::class);
})->with([
    'both' => ['Owl Post', 'OP 4471 2290 88 GB'],
    'a carrier alone' => ['Owl Post', null],
    'a tracking number alone' => [null, 'OP 4471 2290 88 GB'],
]);

it('reads blank shipment details on a step that prints no label as none at all', function (): void {
    $event = NewFulfillmentEvent::stepCompleted(packingStep(), ActorType::Seller, 'sel_MOLLY', flowMoment(), '', '  ');

    expect($event->carrier)->toBeNull()
        ->and($event->trackingNumber)->toBeNull();
});

it('trims the shipment details it records', function (): void {
    $event = NewFulfillmentEvent::stepCompleted(labelStep(), ActorType::Seller, 'sel_MOLLY', flowMoment(), '  Owl Post ', ' OP 4471 ');

    expect($event->carrier)->toBe('Owl Post')
        ->and($event->trackingNumber)->toBe('OP 4471');
});
