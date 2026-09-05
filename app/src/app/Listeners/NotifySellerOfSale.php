<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Notifications\ItemSold;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Every seller with a share of a paid order hears that their item sold, once
 * the order and its escrow are committed.
 */
final readonly class NotifySellerOfSale implements ShouldHandleEventsAfterCommit
{
    public function handle(OrderPaid $event): void
    {
        foreach ($event->order->fulfillments()->with('seller')->get() as $fulfillment) {
            $fulfillment->seller->notify(new ItemSold($event->order->id, $fulfillment->net()));
        }
    }
}
