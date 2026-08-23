<?php

declare(strict_types=1);

namespace App\Models;

it('reads the amount it charged as money', function (): void {
    $order = $this->paidOrderWithTwoSellers();

    expect($order->latestPayment->amount())->toBeMoney(55000);
});

it('reads the order it belongs to', function (): void {
    $order = $this->paidOrderWithTwoSellers();

    expect($order->latestPayment->order->is($order))->toBeTrue();
});
