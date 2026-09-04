<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Money\Money;
use App\Models\Fulfillment;
use DateTimeImmutable;

/**
 * The buyer behind one order, counted across everything they have bought
 * from this seller. A parcel that was turned down or refunded is left out
 * of both the count and the spend: the money went back.
 *
 * A seller sees a name, an email, and an address because an order carries
 * them.
 */
final readonly class CustomerOnOrder
{
    public function facts(Fulfillment $fulfillment): CustomerFacts
    {
        $bought = Fulfillment::query()
            ->where('seller_id', $fulfillment->seller_id)
            ->where('customer_id', $fulfillment->customer_id)
            ->with('order')
            ->get()
            ->filter(fn (Fulfillment $theirs): bool => $theirs->status->isLive());

        $placed = $bought
            ->map(fn (Fulfillment $theirs): DateTimeImmutable => $theirs->order->placed_at->toDateTimeImmutable())
            ->sort()
            ->first();

        return new CustomerFacts(
            name: $fulfillment->loadMissing('order')->order->shipping_name,
            email: $fulfillment->order->email,
            orders: $bought->count(),
            spend: Money::fromCents($bought->sum(fn (Fulfillment $theirs): int => $theirs->subtotal_cents)),
            since: $placed instanceof DateTimeImmutable ? $placed : null,
        );
    }
}
