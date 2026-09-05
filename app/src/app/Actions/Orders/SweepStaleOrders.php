<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Domain\Orders\OrderStatus;
use App\Logging\StoryEvent;
use App\Models\Order;
use App\Support\Story;
use DateTimeImmutable;

/**
 * Cancels the orders a guest never came back to pay for, handing their stock
 * to the next shopper. Nobody asked for this run, so its lines — and the
 * `order.cancel` each cancelled order writes — are the system's own.
 *
 * Idempotent: it selects `pending_verification` orders and leaves them
 * `cancelled`, so a second run over the same window finds nothing.
 */
final readonly class SweepStaleOrders
{
    public function __construct(private CancelOrder $cancelOrder) {}

    /**
     * @return list<Order>
     */
    public function __invoke(DateTimeImmutable $now, int $staleHours): array
    {
        Story::asSystem();

        $cutoff = $now->modify("-{$staleHours} hours");

        /** @var list<Order> $cancelled */
        $cancelled = Story::for(StoryEvent::OrderSweep)->tell('sweeping orders that were never verified', [
            'cutoff' => $cutoff->format(DateTimeImmutable::ATOM),
            'stale_hours' => $staleHours,
        ], function (Story $story) use ($cutoff, $now): array {
            $cancelled = [];

            foreach ($this->stale($cutoff) as $order) {
                $story->doing('cancelling a stale order', ['order_id' => $order->id]);

                $cancelled[] = ($this->cancelOrder)($order, $now);
            }

            $story->did('swept the stale orders', ['cancelled_count' => count($cancelled)]);

            return $cancelled;
        });

        return $cancelled;
    }

    /**
     * @return list<Order>
     */
    private function stale(DateTimeImmutable $cutoff): array
    {
        return array_values(Order::query()
            ->where('status', OrderStatus::PendingVerification)
            ->where('placed_at', '<=', $cutoff)
            ->orderBy('placed_at')
            ->orderBy('id')
            ->get()
            ->all());
    }
}
