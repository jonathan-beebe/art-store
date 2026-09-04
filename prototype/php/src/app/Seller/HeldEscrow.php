<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Money\Money;
use App\Domain\Orders\FulfillmentStatus;
use App\Domain\Seller\HeldOrder;
use App\Domain\Seller\HeldState;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Seller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Every paid order still holding a seller's money, oldest first, and what
 * they total — the escrow half of the earnings page. `$total` is read from
 * the ledger fold, so it reconciles with the fold even where a stray
 * ledger entry would put the two out of step; every row below it draws
 * from the same paid orders the fold's `held` entries name.
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
        $fulfillments = self::heldParcels($seller)
            ->with(['order.items' => fn (Relation $items) => $items->where('seller_id', $seller->id)])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return new self(
            $seller->escrowBalance()->held,
            array_values($fulfillments->map(self::toHeldOrder(...))->all()),
        );
    }

    /**
     * The same two figures the panel above the rows shows, read without
     * hydrating a parcel — what a page wanting the numbers and none of the
     * rows asks for.
     */
    public static function tallyFor(Seller $seller): HeldTally
    {
        return new HeldTally($seller->escrowBalance()->held, self::heldParcels($seller)->count());
    }

    /**
     * Every paid parcel still holding the seller's money.
     *
     * @return HasMany<Fulfillment, Seller>
     */
    private static function heldParcels(Seller $seller): HasMany
    {
        return $seller->fulfillments()
            ->whereIn('status', [FulfillmentStatus::AwaitingShipment, FulfillmentStatus::Shipped])
            ->whereHas('order', fn (Builder $orders): Builder => $orders->whereIn('status', Order::paidStatuses()));
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
