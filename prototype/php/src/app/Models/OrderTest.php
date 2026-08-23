<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Payments\PaymentStatus;

it('reads its totals as money', function (): void {
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller(), ['price_cents' => 45000]));

    expect($order->subtotal())->toBeMoney(45000)
        ->and($order->total())->toBeMoney(45000);
});

it('reads the latest of its payment attempts', function (): void {
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller()));
    Payment::create([
        'order_id' => $order->id,
        'status' => PaymentStatus::Declined,
        'amount_cents' => $order->total_cents,
        'card_last_four' => '0002',
        'processed_at' => $this->moment('2026-08-20 10:00:00'),
    ]);
    $retry = Payment::create([
        'order_id' => $order->id,
        'status' => PaymentStatus::Approved,
        'amount_cents' => $order->total_cents,
        'card_last_four' => '4242',
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
