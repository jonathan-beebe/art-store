<?php

declare(strict_types=1);

namespace App\Models;

it('reads the price it was bought at as money', function (): void {
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller(), ['price_cents' => 45000]));

    expect($order->items()->sole()->unitPrice())->toBeMoney(45000);
});

it('multiplies the unit price out over the quantity', function (): void {
    $item = new OrderItem(['unit_price_cents' => 45000, 'quantity' => 3]);

    expect($item->lineTotal())->toBeMoney(135000);
});
