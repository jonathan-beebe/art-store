<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Domain\Reports\ActivityTimeline;
use App\Models\Listing;
use App\Models\ListingEvent;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;

final class ListingActivityController extends SellerController
{
    private const WINDOW_DAYS = 14;

    public function __invoke(Listing $listing): View
    {
        $this->authorize('view', $listing);

        return view('seller.listings.show', [
            'listing' => $listing->loadEventCounts(),
            'days' => ActivityTimeline::lastDays(
                $this->eventCountsByDate($listing),
                $this->now(),
                self::WINDOW_DAYS,
            ),
            'windowDays' => self::WINDOW_DAYS,
            'sales' => $this->sales($listing),
        ]);
    }

    /**
     * @return array<string, array<string, int>>
     */
    private function eventCountsByDate(Listing $listing): array
    {
        return $listing->events()
            ->get(['id', 'type', 'occurred_at'])
            ->groupBy(fn (ListingEvent $event): string => $event->occurred_at->format('Y-m-d'))
            ->map(fn (Collection $events): array => $events
                ->countBy(fn (ListingEvent $event): string => $event->type->value)
                ->all())
            ->all();
    }

    /**
     * @return Collection<int, OrderItem>
     */
    private function sales(Listing $listing): Collection
    {
        return OrderItem::query()
            ->where('listing_id', $listing->id)
            ->with('order')
            ->latest('id')
            ->get();
    }
}
