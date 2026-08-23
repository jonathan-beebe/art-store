<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Domain\Reports\ActivityTimeline;
use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\ListingEvent;
use App\Models\OrderItem;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;

final class ListingActivityController extends Controller
{
    private const WINDOW_DAYS = 14;

    public function __invoke(string $listing): View
    {
        $seller = auth('seller')->user();
        $listing = $seller->listings()->withEventCounts()->findOrFail($listing);

        return view('seller.listings.show', [
            'listing' => $listing,
            'days' => ActivityTimeline::lastDays(
                $this->eventCountsByDate($listing),
                new DateTimeImmutable(now()->toDateTimeString()),
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
            ->map(fn ($events) => $events->countBy(fn (ListingEvent $event): string => $event->type->value)->all())
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
