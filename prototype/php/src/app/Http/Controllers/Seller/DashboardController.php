<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Domain\Orders\FulfillmentStatus;
use App\Domain\Reports\ListingStatusTally;
use Illuminate\View\View;

final class DashboardController extends SellerController
{
    private const RECENT_NOTIFICATIONS = 5;

    public function __invoke(): View
    {
        $seller = $this->seller();

        return view('seller.dashboard', [
            'tally' => ListingStatusTally::from($seller->listingCountsByStatus()),
            'openFulfillments' => $seller->fulfillments()->where('status', FulfillmentStatus::AwaitingShipment)->count(),
            'balance' => $seller->escrowBalance(),
            'unreadNotifications' => $seller->notifications()->unread()->count(),
            'notifications' => $seller->notifications()->latest('id')->limit(self::RECENT_NOTIFICATIONS)->get(),
        ]);
    }
}
