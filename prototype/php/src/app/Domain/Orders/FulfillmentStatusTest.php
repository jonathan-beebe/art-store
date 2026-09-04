<?php

declare(strict_types=1);

namespace App\Domain\Orders;

use DomainException;

it('lists the statuses a status may transition to', function (FulfillmentStatus $from, array $expected): void {
    expect($from->transitions())->toBe($expected);
})->with([
    'awaiting shipment ships, is declined, or is refunded' => [
        FulfillmentStatus::AwaitingShipment,
        [FulfillmentStatus::Shipped, FulfillmentStatus::Declined, FulfillmentStatus::Refunded],
    ],
    'shipped is delivered or refunded' => [
        FulfillmentStatus::Shipped,
        [FulfillmentStatus::Delivered, FulfillmentStatus::Refunded],
    ],
    'delivered may still be refunded' => [FulfillmentStatus::Delivered, [FulfillmentStatus::Refunded]],
    'declined is final' => [FulfillmentStatus::Declined, []],
    'refunded is final' => [FulfillmentStatus::Refunded, []],
]);

it('returns the next status on transition', function (): void {
    expect(FulfillmentStatus::AwaitingShipment->transitionTo(FulfillmentStatus::Shipped))
        ->toBe(FulfillmentStatus::Shipped);
});

it('rejects shipping a delivered fulfillment', function (): void {
    expect(fn () => FulfillmentStatus::Delivered->transitionTo(FulfillmentStatus::Shipped))
        ->toThrow(DomainException::class, 'delivered to shipped');
});

it('rejects declining a fulfillment that already shipped', function (): void {
    expect(fn () => FulfillmentStatus::Shipped->transitionTo(FulfillmentStatus::Declined))
        ->toThrow(DomainException::class, 'shipped to declined');
});

it('rejects shipping a declined fulfillment', function (): void {
    expect(fn () => FulfillmentStatus::Declined->transitionTo(FulfillmentStatus::Shipped))
        ->toThrow(DomainException::class, 'declined to shipped');
});

it('rejects refunding what has already been refunded', function (): void {
    expect(fn () => FulfillmentStatus::Refunded->transitionTo(FulfillmentStatus::Refunded))
        ->toThrow(DomainException::class, 'refunded to refunded');
});

it('counts only the statuses an order still rolls up from as live', function (FulfillmentStatus $status, bool $expected): void {
    expect($status->isLive())->toBe($expected);
})->with([
    'awaiting shipment is live' => [FulfillmentStatus::AwaitingShipment, true],
    'shipped is live' => [FulfillmentStatus::Shipped, true],
    'delivered is live' => [FulfillmentStatus::Delivered, true],
    'declined is settled' => [FulfillmentStatus::Declined, false],
    'refunded is settled' => [FulfillmentStatus::Refunded, false],
]);

it('reads its stored value back as a sentence', function (FulfillmentStatus $status, string $expected): void {
    expect($status->label())->toBe($expected);
})->with([
    'awaiting shipment' => [FulfillmentStatus::AwaitingShipment, 'Awaiting shipment'],
    'shipped' => [FulfillmentStatus::Shipped, 'Shipped'],
    'delivered' => [FulfillmentStatus::Delivered, 'Delivered'],
    'declined' => [FulfillmentStatus::Declined, 'Declined'],
    'refunded' => [FulfillmentStatus::Refunded, 'Refunded'],
]);

it('names its seller-portal badge tint', function (FulfillmentStatus $status, string $expected): void {
    expect($status->sellerBadgeTint())->toBe($expected);
})->with([
    'awaiting shipment is yellow' => [FulfillmentStatus::AwaitingShipment, 'yellow'],
    'shipped is blue' => [FulfillmentStatus::Shipped, 'blue'],
    'delivered is green' => [FulfillmentStatus::Delivered, 'green'],
    'declined is gray' => [FulfillmentStatus::Declined, 'gray'],
    'refunded is red' => [FulfillmentStatus::Refunded, 'red'],
]);
