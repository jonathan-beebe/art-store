<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Auth\ActorType;
use App\Events\RefundIssued;
use App\Notifications\PurchaseRefunded;
use App\Notifications\SaleRefunded;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * The counterpart of whoever issued a refund hears about it once the money is
 * committed: the customer always, and the seller when it was an admin who
 * decided rather than the seller themselves.
 */
final readonly class NotifyOfRefund implements ShouldHandleEventsAfterCommit
{
    public function handle(RefundIssued $event): void
    {
        $refund = $event->refund;
        $refund->loadMissing(['order.customer', 'fulfillment.seller']);

        $refund->order->customer->notify(new PurchaseRefunded(
            $refund->order_id,
            $refund->amount(),
            $refund->reason,
        ));

        if ($refund->issuer() === ActorType::Admin) {
            $refund->fulfillment->seller->notify(new SaleRefunded(
                $refund->order_id,
                $refund->amount(),
                $refund->reason,
            ));
        }
    }
}
