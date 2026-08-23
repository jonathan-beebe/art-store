<?php

declare(strict_types=1);

namespace App\Domain\Orders;

it('awaits payment only before a successful charge', function (OrderStatus $status, bool $expected): void {
    expect(OrderPayment::awaitsPayment($status))->toBe($expected);
})->with([
    'pending verification awaits payment' => [OrderStatus::PendingVerification, true],
    'awaiting payment awaits payment' => [OrderStatus::AwaitingPayment, true],
    'payment failed still awaits payment' => [OrderStatus::PaymentFailed, true],
    'paid no longer awaits payment' => [OrderStatus::Paid, false],
    'shipped no longer awaits payment' => [OrderStatus::Shipped, false],
    'delivered no longer awaits payment' => [OrderStatus::Delivered, false],
    'cancelled no longer awaits payment' => [OrderStatus::Cancelled, false],
]);

it('is payable only by a verified purchaser', function (): void {
    expect(OrderPayment::isPayableBy(OrderStatus::PendingVerification, true))->toBeTrue()
        ->and(OrderPayment::isPayableBy(OrderStatus::PendingVerification, false))->toBeFalse();
});

it('is not payable once paid, even by a verified purchaser', function (): void {
    expect(OrderPayment::isPayableBy(OrderStatus::Paid, true))->toBeFalse();
});
