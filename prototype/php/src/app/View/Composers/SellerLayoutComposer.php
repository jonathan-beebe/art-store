<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Domain\Orders\FulfillmentStatus;
use App\Models\Message;
use App\Models\Seller;
use Illuminate\View\View;

/**
 * The chrome counts the seller layout carries on every page: unread
 * messages and orders awaiting shipment for the left rail's count chips,
 * and whether the bell should show its unread dot. Bound to the seller
 * layout, so a page renders them without its controller passing them
 * along. Every count is a cheap count/exists query — no eager loads.
 */
final readonly class SellerLayoutComposer
{
    public function compose(View $view): void
    {
        $seller = auth('seller')->user();

        if (! $seller instanceof Seller) {
            return;
        }

        $view->with([
            'unreadMessageCount' => Message::query()->unreadInInboxOf($seller)->count(),
            'awaitingShipmentCount' => $seller->fulfillments()->where('status', FulfillmentStatus::AwaitingShipment)->count(),
            'hasUnreadNotifications' => $seller->unreadNotifications()->exists(),
        ]);
    }
}
