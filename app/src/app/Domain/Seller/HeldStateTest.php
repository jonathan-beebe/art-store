<?php

declare(strict_types=1);

namespace App\Domain\Seller;

use App\Domain\Orders\FulfillmentStatus;

it('reads shipped alone as in transit', function (): void {
    expect(HeldState::of(FulfillmentStatus::Shipped))->toBe(HeldState::InTransit);
});

it('reads every other held status as not yet shipped', function (FulfillmentStatus $status): void {
    expect(HeldState::of($status))->toBe(HeldState::NotYetShipped);
})->with([
    'awaiting shipment' => [FulfillmentStatus::AwaitingShipment],
    'delivered' => [FulfillmentStatus::Delivered],
    'declined' => [FulfillmentStatus::Declined],
    'refunded' => [FulfillmentStatus::Refunded],
]);
