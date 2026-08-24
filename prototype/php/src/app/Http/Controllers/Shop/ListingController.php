<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Actions\Listings\RecordListingEvent;
use App\Domain\Listings\ListingAvailability;
use App\Domain\Listings\ListingEventType;
use App\Logging\StoryEvent;
use App\Models\Listing;
use App\Support\Story;
use Illuminate\Contracts\View\View;

final class ListingController extends ShopController
{
    public function __invoke(Listing $listing, RecordListingEvent $recordListingEvent): View
    {
        abort_unless($listing->status->isOnStorefront(), 404);

        $visitor = $this->visitor();
        $recordListingEvent($listing, $visitor->id, ListingEventType::View, $this->now());

        // One line per view. The once-per-hour collapse §2.3 describes, and
        // the `refused` line that records it, arrive with the roll-up in
        // FEAT-023.
        Story::for(StoryEvent::ListingView)->did('viewed a listing', [
            'listing_id' => $listing->id,
            'seller_id' => $listing->seller_id,
            'status' => $listing->status->value,
        ]);

        return view('shop.listing', [
            'listing' => $listing->load('seller', 'faqs'),
            'isPurchasable' => ListingAvailability::isPurchasable($listing->status, $listing->quantity),
            'isFavorited' => $visitor->favorites()->where('listing_id', $listing->id)->exists(),
        ]);
    }
}
