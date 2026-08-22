<?php

namespace App\Http\Controllers\Seller;

use App\Domain\Orders\FulfillmentStatus;
use App\Domain\Reports\ListingStatusTally;
use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\Seller;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    private const RECENT_NOTIFICATIONS = 5;

    public function __invoke(): View
    {
        $seller = auth('seller')->user();

        return view('seller.dashboard', [
            'tally' => ListingStatusTally::from($this->listingCountsByStatus($seller)),
            'openFulfillments' => $seller->fulfillments()->where('status', FulfillmentStatus::AwaitingShipment)->count(),
            'balance' => $seller->escrowBalance(),
            'unreadNotifications' => $seller->notifications()->unread()->count(),
            'notifications' => $seller->notifications()->latest('id')->limit(self::RECENT_NOTIFICATIONS)->get(),
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function listingCountsByStatus(Seller $seller): array
    {
        return $seller->listings()
            ->get(['id', 'status'])
            ->countBy(fn (Listing $listing): string => $listing->status->value)
            ->all();
    }
}
