<?php

declare(strict_types=1);

namespace App\Models;

it('reads the amount it charged as money', function (): void {
    $order = $this->paidOrderWithTwoSellers();

    expect($order->latestPayment()->sole()->amount())->toBeMoney(55000);
});
