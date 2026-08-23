<?php

declare(strict_types=1);

namespace App\Models;

it('reads its totals as money', function (): void {
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller(), ['price_cents' => 45000]));

    expect($order->subtotal())->toBeMoney(45000)
        ->and($order->total())->toBeMoney(45000);
});

it('reads the latest of its payment attempts', function (): void {
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller()));
    Payment::factory()->declined()->create([
        'order_id' => $order->id,
        'amount_cents' => $order->total_cents,
        'processed_at' => $this->moment('2026-08-20 10:00:00'),
    ]);
    $retry = Payment::factory()->approved()->create([
        'order_id' => $order->id,
        'amount_cents' => $order->total_cents,
        'processed_at' => $this->moment('2026-08-20 10:05:00'),
    ]);

    expect($order->latestPayment->is($retry))->toBeTrue();
});

it('has no latest payment before the first attempt', function (): void {
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller()));

    expect($order->latestPayment)->toBeNull();
});

it('reads the items, fulfillments, and customer behind it', function (): void {
    $customer = $this->verifiedCustomer();
    $order = $this->orderFor($customer, $this->listing($this->seller()));

    expect($order->items()->count())->toBe(1)
        ->and($order->fulfillments()->count())->toBe(1)
        ->and($order->customer->is($customer))->toBeTrue();
});
