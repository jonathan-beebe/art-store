<?php

declare(strict_types=1);

namespace App\Domain\Orders;

use App\Domain\Payments\PaymentOutcome;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

it('lists the statuses a status may transition to', function (OrderStatus $from, array $expected): void {
    expect($from->transitions())->toBe($expected);
})->with([
    'unverified may be charged or cancelled' => [OrderStatus::PendingVerification, [OrderStatus::Paid, OrderStatus::PaymentFailed, OrderStatus::Cancelled]],
    'awaiting payment may be charged or cancelled' => [OrderStatus::AwaitingPayment, [OrderStatus::Paid, OrderStatus::PaymentFailed, OrderStatus::Cancelled]],
    'a failed payment may be retried or cancelled' => [OrderStatus::PaymentFailed, [OrderStatus::Paid, OrderStatus::Cancelled]],
    'paid moves into shipping' => [OrderStatus::Paid, [OrderStatus::PartiallyShipped, OrderStatus::Shipped]],
    'partially shipped completes shipping' => [OrderStatus::PartiallyShipped, [OrderStatus::Shipped]],
    'shipped is delivered next' => [OrderStatus::Shipped, [OrderStatus::Delivered]],
    'delivered is final' => [OrderStatus::Delivered, []],
    'cancelled is final' => [OrderStatus::Cancelled, []],
]);

it('agrees with the transition table on every pair', function (): void {
    foreach (OrderStatus::cases() as $from) {
        foreach (OrderStatus::cases() as $to) {
            expect($from->canTransitionTo($to))
                ->toBe(in_array($to, $from->transitions(), true), "{$from->value} -> {$to->value}");
        }
    }
});

it('returns the next status on transition', function (): void {
    expect(OrderStatus::PaymentFailed->transitionTo(OrderStatus::Paid))->toBe(OrderStatus::Paid);
});

it('rejects a move outside the table', function (): void {
    expect(fn () => OrderStatus::Delivered->transitionTo(OrderStatus::Paid))
        ->toThrow(DomainException::class, 'delivered to paid');
});

it('places a verified purchaser order ready to charge', function (): void {
    $purchaser = new Purchaser(1, 'buyer@example.test', new DateTimeImmutable('2026-08-22 10:00:00'));

    expect(OrderStatus::forPlacement($purchaser))->toBe(OrderStatus::AwaitingPayment);
});

it('places an unverified purchaser order that waits for verification', function (): void {
    $purchaser = new Purchaser(1, null, null);

    expect(OrderStatus::forPlacement($purchaser))->toBe(OrderStatus::PendingVerification);
});

it('pays the order on an approved payment outcome', function (): void {
    expect(OrderStatus::fromCardDecision(PaymentOutcome::Approved))->toBe(OrderStatus::Paid);
});

it('fails the payment on a declined payment outcome', function (): void {
    expect(OrderStatus::fromCardDecision(PaymentOutcome::Declined))->toBe(OrderStatus::PaymentFailed);
});

it('rolls up fulfillment statuses into an order status', function (array $fulfillments, OrderStatus $expected): void {
    expect(OrderStatus::fromFulfillments($fulfillments))->toBe($expected);
})->with([
    'all awaiting shipment leaves the order paid' => [
        [FulfillmentStatus::AwaitingShipment, FulfillmentStatus::AwaitingShipment],
        OrderStatus::Paid,
    ],
    'one shipped of several partially ships the order' => [
        [FulfillmentStatus::Shipped, FulfillmentStatus::AwaitingShipment],
        OrderStatus::PartiallyShipped,
    ],
    'one delivered of several partially ships the order' => [
        [FulfillmentStatus::Delivered, FulfillmentStatus::AwaitingShipment],
        OrderStatus::PartiallyShipped,
    ],
    'every fulfillment shipped ships the order' => [
        [FulfillmentStatus::Shipped, FulfillmentStatus::Shipped],
        OrderStatus::Shipped,
    ],
    'a mix of shipped and delivered ships the order' => [
        [FulfillmentStatus::Delivered, FulfillmentStatus::Shipped],
        OrderStatus::Shipped,
    ],
    'every fulfillment delivered delivers the order' => [
        [FulfillmentStatus::Delivered, FulfillmentStatus::Delivered],
        OrderStatus::Delivered,
    ],
]);

it('needs at least one fulfillment to roll up', function (): void {
    expect(fn () => OrderStatus::fromFulfillments([]))->toThrow(InvalidArgumentException::class);
});
