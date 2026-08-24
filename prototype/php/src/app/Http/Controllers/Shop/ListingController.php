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

        // Every view writes its own line: nothing here collapses the repeat
        // views one customer makes within an hour into a single one.
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
