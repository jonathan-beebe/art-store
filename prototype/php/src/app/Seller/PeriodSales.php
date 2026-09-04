<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Escrow\PayoutPeriod;
use App\Domain\Seller\PeriodSaleRow;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Seller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use RuntimeException;

/**
 * Every order placed inside one payout period, newest first — the rows
 * behind that period's sales table and its printable statement. Every
 * status is included, live or not: a declined order still placed that
 * period, and a statement is a record of what happened rather than only
 * what paid.
 */
final readonly class PeriodSales
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * @return list<PeriodSaleRow>
     */
    public static function for(Seller $seller, PayoutPeriod $period): array
    {
        return array_values($seller->fulfillments()
            ->whereHas('order', fn (Builder $orders): Builder => $orders->whereBetween('placed_at', [$period->start, $period->end]))
            ->with(['order.items' => fn (Relation $items) => $items->where('seller_id', $seller->id)])
            ->get()
            ->sortByDesc(fn (Fulfillment $fulfillment): string => $fulfillment->order->placed_at->format('Y-m-d H:i:s.u').$fulfillment->id)
            ->map(self::toRow(...))
            ->all());
    }

    private static function toRow(Fulfillment $fulfillment): PeriodSaleRow
    {
        /** @var Order $order */
        $order = $fulfillment->order;
        $placedAt = $order->placed_at ?? throw new RuntimeException('An order behind a fulfillment always carries a placed_at.');

        return new PeriodSaleRow(
            $fulfillment->id,
            $placedAt->toDateTimeImmutable(),
            $order->shipping_name,
            implode(', ', $order->items->map(fn (OrderItem $item): string => $item->title)->all()),
            $fulfillment->subtotal(),
            $fulfillment->fee(),
            $fulfillment->net(),
            $fulfillment->status,
        );
    }
}
