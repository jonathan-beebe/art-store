<?php

declare(strict_types=1);

namespace App\Domain\Orders;

use DomainException;

it('lists the statuses a status may transition to', function (FulfillmentStatus $from, array $expected): void {
    expect($from->transitions())->toBe($expected);
})->with([
    'awaiting shipment ships next' => [FulfillmentStatus::AwaitingShipment, [FulfillmentStatus::Shipped]],
    'shipped is delivered next' => [FulfillmentStatus::Shipped, [FulfillmentStatus::Delivered]],
    'delivered is final' => [FulfillmentStatus::Delivered, []],
]);

it('agrees with the transition table on every pair', function (): void {
    foreach (FulfillmentStatus::cases() as $from) {
        foreach (FulfillmentStatus::cases() as $to) {
            expect($from->canTransitionTo($to))
                ->toBe(in_array($to, $from->transitions(), true), "{$from->value} -> {$to->value}");
        }
    }
});

it('returns the next status on transition', function (): void {
    expect(FulfillmentStatus::AwaitingShipment->transitionTo(FulfillmentStatus::Shipped))
        ->toBe(FulfillmentStatus::Shipped);
});

it('rejects shipping a delivered fulfillment', function (): void {
    expect(fn () => FulfillmentStatus::Delivered->transitionTo(FulfillmentStatus::Shipped))
        ->toThrow(DomainException::class, 'delivered to shipped');
});
