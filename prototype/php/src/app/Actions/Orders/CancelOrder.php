<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Domain\Orders\OrderStatus;
use App\Events\OrderCancelled;
use App\Logging\StoryEvent;
use App\Models\Listing;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\Story;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Ends an order that was never paid — by the customer, by an admin, or by the
 * stale sweep. Who asked is on the log line's `actor_type`, which the request
 * middleware or the sweep has already put in the logger's context.
 */
final readonly class CancelOrder
{
    public function __invoke(Order $order, DateTimeImmutable $now): Order
    {
        return Story::for(StoryEvent::OrderCancel)->tell('cancelling an order', [
            'order_id' => $order->id,
            'status_from' => $order->status->value,
            'status_to' => OrderStatus::Cancelled->value,
        ], function (Story $story) use ($order, $now): Order {
            $cancelled = DB::transaction(function () use ($order, $now): Order {
                // The status is read again here, under the transaction that
                // writes it: an order paid between the page and this submit
                // is refused rather than cancelled out from under the money.
                $status = $order->refresh()->status;

                if ($status->releasesStockOnCancel()) {
                    $this->restockItems($order);
                }

                $order->update(['status' => $status->transitionTo(OrderStatus::Cancelled)]);

                OrderCancelled::dispatch($order, $now);

                return $order;
            });

            $story->did('cancelled the order', [
                'order_id' => $cancelled->id,
                'status_to' => $cancelled->status->value,
            ]);

            return $cancelled;
        });
    }

    /**
     * Hands back every listing this order was holding, from rows read for
     * update so the quantity written is the quantity read.
     */
    private function restockItems(Order $order): void
    {
        foreach ($order->load(['items.listing' => $this->takeForUpdate(...)])->items as $item) {
            $item->listing->restock($item->quantity);
        }
    }

    /**
     * @param  BelongsTo<Listing, OrderItem>  $listing
     */
    private function takeForUpdate(BelongsTo $listing): void
    {
        $listing->getQuery()->lockedForPlacement();
    }
}
