<?php

declare(strict_types=1);

namespace App\Domain\Orders;

it('is payable only by a verified purchaser', function (): void {
    expect(OrderPayment::isPayableBy(OrderStatus::PendingVerification, true))->toBeTrue()
        ->and(OrderPayment::isPayableBy(OrderStatus::PendingVerification, false))->toBeFalse();
});

it('is not payable once paid, even by a verified purchaser', function (): void {
    expect(OrderPayment::isPayableBy(OrderStatus::Paid, true))->toBeFalse();
});
