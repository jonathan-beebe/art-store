<?php

declare(strict_types=1);

namespace App\Domain\Reports;

use App\Domain\Orders\OrderStatus;

it('carries a status and its count, and labels itself by the status', function (): void {
    $count = OrderStatusCount::of(OrderStatus::PaymentFailed, 2);

    expect($count->status)->toBe(OrderStatus::PaymentFailed)
        ->and($count->count)->toBe(2)
        ->and($count->label())->toBe('Payment failed');
});
