<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Domain\Reports\ActivityTimeline;
use App\Models\Listing;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;

final class ListingActivityController extends SellerController
{
    private const WINDOW_DAYS = 14;

    public function __invoke(Listing $listing): View
    {
        $this->authorize('view', $listing);

        $endsOn = $this->now();

        return view('seller.listings.show', [
            'listing' => $listing->loadEventCounts(),
            'days' => ActivityTimeline::lastDays(
                $listing->eventCountsByDateSince(ActivityTimeline::firstDay($endsOn, self::WINDOW_DAYS)),
                $endsOn,
                self::WINDOW_DAYS,
            ),
            'windowDays' => self::WINDOW_DAYS,
            'sales' => $this->sales($listing),
        ]);
    }

    /**
     * @return Collection<int, OrderItem>
     */
    private function sales(Listing $listing): Collection
    {
        return $listing->orderItems()
            ->with('order')
            ->latest('id')
            ->get();
    }
}
