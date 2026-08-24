<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OrderCancelled;
use App\Notifications\PurchaseCancelled;
use App\Notifications\SaleCancelled;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Both sides of a cancelled order hear about it once the cancellation and the
 * stock it handed back are committed: the customer who was going to pay, and
 * every seller whose pieces are back on the storefront.
 */
final readonly class NotifyOfCancellation implements ShouldHandleEventsAfterCommit
{
    public function handle(OrderCancelled $event): void
    {
        $order = $event->order;

        $order->loadMissing('customer')->customer->notify(new PurchaseCancelled($order->id));

        foreach ($order->fulfillments()->with('seller')->get() as $fulfillment) {
            $fulfillment->seller->notify(new SaleCancelled($order->id));
        }
    }
}
