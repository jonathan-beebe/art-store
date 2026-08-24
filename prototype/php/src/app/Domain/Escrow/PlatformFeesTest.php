<?php

declare(strict_types=1);

namespace App\Domain\Escrow;

use App\Domain\Orders\FulfillmentStatus;

it('earns the fee on a fulfillment still live', function (FulfillmentStatus $status): void {
    $fees = PlatformFees::from([['status' => $status, 'feeCents' => 1000]]);

    expect($fees->earned->cents)->toBe(1000)
        ->and($fees->refunded->cents)->toBe(0);
})->with([
    'awaiting shipment' => [FulfillmentStatus::AwaitingShipment],
    'shipped' => [FulfillmentStatus::Shipped],
    'delivered' => [FulfillmentStatus::Delivered],
]);

it('forgoes the fee on a fulfillment that was declined or refunded', function (FulfillmentStatus $status): void {
    $fees = PlatformFees::from([['status' => $status, 'feeCents' => 1000]]);

    expect($fees->earned->cents)->toBe(0)
        ->and($fees->refunded->cents)->toBe(1000);
})->with([
    'declined' => [FulfillmentStatus::Declined],
    'refunded' => [FulfillmentStatus::Refunded],
]);

it('sums across every fulfillment it is given', function (): void {
    $fees = PlatformFees::from([
        ['status' => FulfillmentStatus::Delivered, 'feeCents' => 1000],
        ['status' => FulfillmentStatus::Shipped, 'feeCents' => 500],
        ['status' => FulfillmentStatus::Declined, 'feeCents' => 300],
        ['status' => FulfillmentStatus::Refunded, 'feeCents' => 200],
    ]);

    expect($fees->earned->cents)->toBe(1500)
        ->and($fees->refunded->cents)->toBe(500);
});

it('earns and forgoes nothing over no fulfillments at all', function (): void {
    $fees = PlatformFees::from([]);

    expect($fees->earned->cents)->toBe(0)
        ->and($fees->refunded->cents)->toBe(0);
});
