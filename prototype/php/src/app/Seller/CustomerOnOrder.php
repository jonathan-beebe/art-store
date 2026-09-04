<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Money\Money;
use App\Domain\Orders\FulfillmentStatus;
use App\Models\Fulfillment;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * The buyer behind one order, counted across everything they have bought
 * from this seller. A parcel that was turned down or refunded is left out
 * of the count, the spend, and the day they became a customer: the money
 * went back.
 *
 * A seller sees a name, an email, and an address because an order carries
 * them.
 */
final readonly class CustomerOnOrder
{
    public function facts(Fulfillment $fulfillment): CustomerFacts
    {
        $order = $fulfillment->order;

        /** @var object{orders: int, spend_cents: int|null, since: string|null} $bought  an aggregate with no grouping answers one row */
        $bought = $this->theirParcels($fulfillment)
            ->join('orders', 'orders.id', '=', 'fulfillments.order_id')
            ->selectRaw('count(*) as orders, sum(fulfillments.subtotal_cents) as spend_cents, min(orders.placed_at) as since')
            ->toBase()
            ->first();

        $since = $bought->since;

        return new CustomerFacts(
            name: $order->shipping_name,
            email: $order->email,
            orders: $bought->orders,
            spend: Money::fromCents($bought->spend_cents ?? 0),
            since: is_string($since) ? new DateTimeImmutable($since) : null,
        );
    }

    /**
     * Every parcel this seller has shipped, or owes, this buyer.
     *
     * @return Builder<Fulfillment>
     */
    private function theirParcels(Fulfillment $fulfillment): Builder
    {
        return Fulfillment::query()
            ->where('fulfillments.seller_id', $fulfillment->seller_id)
            ->where('fulfillments.customer_id', $fulfillment->customer_id)
            ->whereNotIn('fulfillments.status', [FulfillmentStatus::Declined, FulfillmentStatus::Refunded]);
    }
}
