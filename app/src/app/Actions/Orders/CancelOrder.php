<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Analytics\Analytics;
use App\Analytics\AnalyticsEvent;
use App\Domain\Analytics\AnalyticsEventName;
use App\Domain\Orders\OrderStatus;
use App\Events\OrderCancelled;
use App\Logging\StoryEvent;
use App\Models\Listing;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Unit;
use App\Models\Variant;
use App\Support\Orders\OrderListingIds;
use App\Support\Orders\StockMovement;
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
    public function __construct(private Analytics $analytics) {}

    public function __invoke(Order $order, DateTimeImmutable $now): Order
    {
        return Story::for(StoryEvent::OrderCancel)->tell('cancelling an order', [
            'order_id' => $order->id,
            'status_from' => $order->status->value,
            'status_to' => OrderStatus::Cancelled->value,
        ], function (Story $story) use ($order, $now): Order {
            $cancelled = DB::transaction(function () use ($order, $now): Order {
                // The status is read again here, under the transaction that
                // writes it, so an order paid between the page and this
                // submit is refused. Money that has already moved stays
                // untouched.
                $status = $order->refresh()->status;

                if ($status->releasesStockOnCancel()) {
                    $this->restockItems($order);
                }

                $order->update(['status' => $status->transitionTo(OrderStatus::Cancelled)]);

                OrderCancelled::dispatch($order, $now);

                return $order;
            });

            $this->analytics->recordEvent(AnalyticsEvent::forOrder(
                AnalyticsEventName::OrderCancel,
                $cancelled->id,
                $cancelled->customer_id,
                $now,
                ['listing_ids' => OrderListingIds::of($cancelled)],
            ));

            $story->did('cancelled the order', [
                'order_id' => $cancelled->id,
                'status_to' => $cancelled->status->value,
            ]);

            return $cancelled;
        });
    }

    /**
     * Hands back every listing (or, for a configured line, variant/unit)
     * this order was holding, from rows read for update so the quantity
     * written is the quantity read.
     */
    private function restockItems(Order $order): void
    {
        $order->load([
            'items.listing' => $this->takeForUpdate(...),
            'items.variant' => $this->takeForUpdateVariant(...),
            'items.unit' => $this->takeForUpdateUnit(...),
        ]);

        foreach ($order->items as $item) {
            StockMovement::release($item);
        }
    }

    /**
     * @param  BelongsTo<Listing, OrderItem>  $listing
     */
    private function takeForUpdate(BelongsTo $listing): void
    {
        $listing->getQuery()->lockedForPlacement();
    }

    /**
     * @param  BelongsTo<Variant, OrderItem>  $variant
     */
    private function takeForUpdateVariant(BelongsTo $variant): void
    {
        $variant->getQuery()->lockedForPlacement();
    }

    /**
     * @param  BelongsTo<Unit, OrderItem>  $unit
     */
    private function takeForUpdateUnit(BelongsTo $unit): void
    {
        $unit->getQuery()->lockedForPlacement();
    }
}
