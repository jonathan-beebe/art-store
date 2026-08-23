<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\FulfillmentShipped;
use App\Notifications\OrderShipped;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * The customer behind a shipped fulfillment hears it is on its way, once the
 * shipment and the order status it rolled up are committed.
 */
final readonly class NotifyCustomerOfShipment implements ShouldHandleEventsAfterCommit
{
    public function handle(FulfillmentShipped $event): void
    {
        $fulfillment = $event->fulfillment;
        $fulfillment->loadMissing('order.customer');
        $order = $fulfillment->order;

        $order->customer->notify(new OrderShipped(
            $order->id,
            (string) $fulfillment->carrier,
            (string) $fulfillment->tracking_number,
        ));
    }
}
