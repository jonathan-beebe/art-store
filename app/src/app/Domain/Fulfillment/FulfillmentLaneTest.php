<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment;

use App\Domain\Orders\FulfillmentStatus;

it('sorts a fulfillment into the lane its status and its progress spell together', function (FulfillmentStatus $status, bool $started, FulfillmentLane $lane): void {
    $steps = [new FlowStep('ffs_label', 'label', 'Label printed', FlowStepAction::PrintLabel, 0)];

    $progress = FulfillmentProgress::of($steps, $started ? ['ffs_label'] : []);

    expect(FulfillmentLane::of($status, $progress))->toBe($lane);
})->with([
    'awaiting shipment, nothing done' => [FulfillmentStatus::AwaitingShipment, false, FulfillmentLane::ToShip],
    'awaiting shipment, a step done' => [FulfillmentStatus::AwaitingShipment, true, FulfillmentLane::InProgress],
    'shipped, nothing done' => [FulfillmentStatus::Shipped, false, FulfillmentLane::InProgress],
    'shipped, a step done' => [FulfillmentStatus::Shipped, true, FulfillmentLane::InProgress],
    'delivered' => [FulfillmentStatus::Delivered, true, FulfillmentLane::Done],
    'declined' => [FulfillmentStatus::Declined, false, FulfillmentLane::Done],
    'refunded' => [FulfillmentStatus::Refunded, true, FulfillmentLane::Done],
]);

it('puts a parcel with no steps to ship while it awaits shipment', function (): void {
    $progress = FulfillmentProgress::of([], []);

    expect(FulfillmentLane::of(FulfillmentStatus::AwaitingShipment, $progress))->toBe(FulfillmentLane::ToShip);
});

it('reads the same lane off the two facts a grouped count carries', function (FulfillmentStatus $status, bool $started, FulfillmentLane $lane): void {
    expect(FulfillmentLane::forStarted($status, $started))->toBe($lane);
})->with([
    'awaiting shipment, nothing done' => [FulfillmentStatus::AwaitingShipment, false, FulfillmentLane::ToShip],
    'awaiting shipment, a step done' => [FulfillmentStatus::AwaitingShipment, true, FulfillmentLane::InProgress],
    'shipped, nothing done' => [FulfillmentStatus::Shipped, false, FulfillmentLane::InProgress],
    'shipped, a step done' => [FulfillmentStatus::Shipped, true, FulfillmentLane::InProgress],
    'delivered' => [FulfillmentStatus::Delivered, true, FulfillmentLane::Done],
    'declined' => [FulfillmentStatus::Declined, false, FulfillmentLane::Done],
    'refunded' => [FulfillmentStatus::Refunded, true, FulfillmentLane::Done],
]);

it('names each lane', function (FulfillmentLane $lane, string $label): void {
    expect($lane->label())->toBe($label);
})->with([
    [FulfillmentLane::ToShip, 'To ship'],
    [FulfillmentLane::InProgress, 'In progress'],
    [FulfillmentLane::Done, 'Done'],
]);
