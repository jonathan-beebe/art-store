<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Money\Money;
use App\Domain\Orders\FulfillmentStatus;
use App\Domain\Seller\HeldOrder;
use App\Domain\Seller\HeldState;
use App\Models\Fulfillment;
use App\Models\OrderItem;
use App\Models\Seller;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Every order still holding a seller's money, oldest first, and what they
 * total — the escrow half of the earnings page. `$total` is read from the
 * ledger fold rather than summed from the rows below it, so the figure
 * reconciles with the fold even where a stray ledger entry would make the
 * two diverge.
 */
final readonly class HeldEscrow
{
    /**
     * @param  list<HeldOrder>  $orders
     */
    private function __construct(
        public Money $total,
        public array $orders,
    ) {}

    public static function for(Seller $seller): self
    {
        $fulfillments = $seller->fulfillments()
            ->whereIn('status', [FulfillmentStatus::AwaitingShipment, FulfillmentStatus::Shipped])
            ->with(['order.items' => fn (Relation $items) => $items->where('seller_id', $seller->id)])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return new self(
            $seller->escrowBalance()->held,
            array_values($fulfillments->map(self::toHeldOrder(...))->all()),
        );
    }

    private static function toHeldOrder(Fulfillment $fulfillment): HeldOrder
    {
        return new HeldOrder(
            $fulfillment->id,
            $fulfillment->order->shipping_name,
            implode(', ', $fulfillment->order->items->map(fn (OrderItem $item): string => $item->title)->all()),
            $fulfillment->net(),
            HeldState::of($fulfillment->status),
            $fulfillment->shipped_at?->toDateTimeImmutable(),
        );
    }
}
