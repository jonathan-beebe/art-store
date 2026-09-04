<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment;

it('opens on the pile the seller has to act on', function (): void {
    expect(LaneFilter::default())->toBe(LaneFilter::ToShip);
});

it('carries the lane behind each tab', function (LaneFilter $filter, ?FulfillmentLane $lane): void {
    expect($filter->lane())->toBe($lane);
})->with([
    [LaneFilter::ToShip, FulfillmentLane::ToShip],
    [LaneFilter::InProgress, FulfillmentLane::InProgress],
    [LaneFilter::Done, FulfillmentLane::Done],
    [LaneFilter::All, null],
]);

it('answers with the tab a lane belongs to', function (FulfillmentLane $lane, LaneFilter $filter): void {
    expect(LaneFilter::of($lane))->toBe($filter);
})->with([
    [FulfillmentLane::ToShip, LaneFilter::ToShip],
    [FulfillmentLane::InProgress, LaneFilter::InProgress],
    [FulfillmentLane::Done, LaneFilter::Done],
]);

it('takes each tab label from the lane behind it', function (LaneFilter $filter, string $label): void {
    expect($filter->label())->toBe($label);
})->with([
    [LaneFilter::ToShip, 'To ship'],
    [LaneFilter::InProgress, 'In progress'],
    [LaneFilter::Done, 'Done'],
    [LaneFilter::All, 'All'],
]);

it('counts only the tabs that ask for work', function (LaneFilter $filter, bool $counted): void {
    expect($filter->isCounted())->toBe($counted);
})->with([
    [LaneFilter::ToShip, true],
    [LaneFilter::InProgress, true],
    [LaneFilter::Done, false],
    [LaneFilter::All, false],
]);

it('reads the oldest parcel first only where a buyer is waiting', function (LaneFilter $filter, bool $oldestFirst): void {
    expect($filter->oldestFirst())->toBe($oldestFirst);
})->with([
    [LaneFilter::ToShip, true],
    [LaneFilter::InProgress, false],
    [LaneFilter::Done, false],
    [LaneFilter::All, false],
]);

it('says what each empty tab is empty of', function (LaneFilter $filter, string $message): void {
    expect($filter->emptyMessage())->toBe($message);
})->with([
    [LaneFilter::ToShip, 'Nothing to ship.'],
    [LaneFilter::InProgress, 'Nothing on its way.'],
    [LaneFilter::Done, 'Nothing finished yet.'],
    [LaneFilter::All, 'No orders yet.'],
]);
