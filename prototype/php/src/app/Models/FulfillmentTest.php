<?php

declare(strict_types=1);

namespace App\Models;

it('reads its subtotal, the platform fee, and the seller net as money', function (): void {
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller(), ['price_cents' => 45000]));
    $fulfillment = $order->fulfillments()->sole();

    expect($fulfillment->subtotal())->toBeMoney(45000)
        ->and($fulfillment->fee())->toBeMoney(4500)
        ->and($fulfillment->net())->toBeMoney(40500);
});

it('adds the fee and the net back up to the subtotal', function (): void {
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller(), ['price_cents' => 4599]));
    $fulfillment = $order->fulfillments()->sole();

    expect($fulfillment->fee()->add($fulfillment->net())->equals($fulfillment->subtotal()))->toBeTrue();
});

it('reads the ledger entries it produced', function (): void {
    $fulfillment = $this->deliveredFulfillmentFor($this->seller(), priceCents: 10000);

    expect($fulfillment->ledgerEntries()->count())->toBe(2);
});
