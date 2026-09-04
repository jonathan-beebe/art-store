<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment;

use App\Domain\DomainRuleViolation;
use App\Domain\Orders\FulfillmentStatus;

it('names the event kind that records each transition', function (FulfillmentStatus $status, FulfillmentEventKind $kind): void {
    expect(FulfillmentEventKind::forStatus($status))->toBe($kind);
})->with([
    [FulfillmentStatus::Shipped, FulfillmentEventKind::Shipped],
    [FulfillmentStatus::Delivered, FulfillmentEventKind::Delivered],
    [FulfillmentStatus::Declined, FulfillmentEventKind::Declined],
    [FulfillmentStatus::Refunded, FulfillmentEventKind::Refunded],
]);

it('refuses to name a transition into the status a fulfillment starts in', function (): void {
    expect(fn () => FulfillmentEventKind::forStatus(FulfillmentStatus::AwaitingShipment))
        ->toThrow(DomainRuleViolation::class);
});

it('knows which kind carries a step', function (): void {
    expect(FulfillmentEventKind::StepCompleted->namesAStep())->toBeTrue()
        ->and(FulfillmentEventKind::Shipped->namesAStep())->toBeFalse();
});

it('reads each kind back as a sentence fragment', function (FulfillmentEventKind $kind, string $label): void {
    expect($kind->label())->toBe($label);
})->with([
    [FulfillmentEventKind::StepCompleted, 'Step completed'],
    [FulfillmentEventKind::Shipped, 'Shipped'],
    [FulfillmentEventKind::Delivered, 'Delivered'],
    [FulfillmentEventKind::Declined, 'Declined'],
    [FulfillmentEventKind::Refunded, 'Refunded'],
]);
