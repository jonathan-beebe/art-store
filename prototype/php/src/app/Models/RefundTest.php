<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Auth\ActorType;

it('mints a prefixed id', function (): void {
    expect(Refund::factory()->create()->id)->toStartWith('rfd_');
});

it('reads its amount as money', function (): void {
    expect(Refund::factory()->create(['amount_cents' => 12500])->amount())->toBeMoney(12500);
});

it('names the order, fulfillment, and payment it settles', function (): void {
    $fulfillment = $this->paidFulfillmentFor($this->seller());
    $payment = $fulfillment->order->payments()->sole();
    $refund = Refund::factory()->create([
        'order_id' => $fulfillment->order_id,
        'fulfillment_id' => $fulfillment->id,
        'payment_id' => $payment->id,
    ]);

    expect($refund->order->is($fulfillment->order))->toBeTrue()
        ->and($refund->fulfillment->is($fulfillment))->toBeTrue()
        ->and($refund->payment()->sole()->is($payment))->toBeTrue();
});

it('reads back who issued it', function (): void {
    $admin = $this->admin();
    $refund = Refund::factory()->byAdmin($admin->id)->create();

    expect($refund->issuer())->toBe(ActorType::Admin)
        ->and($refund->issued_by_id)->toBe($admin->id)
        ->and($refund->issuerLabel())->toBe('Admin');
});

it('reads a seller-issued refund back as the seller\'s', function (): void {
    expect(Refund::factory()->create()->issuerLabel())->toBe('Seller');
});
