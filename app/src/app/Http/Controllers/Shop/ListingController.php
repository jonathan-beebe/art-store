<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Analytics\Analytics;
use App\Analytics\AnalyticsEvent;
use App\Configurator\ListingPagePresenter;
use App\Domain\Analytics\AnalyticsEventName;
use App\Domain\Listings\ListingViewCollapse;
use App\Logging\Story;
use App\Logging\StoryEvent;
use App\Models\Listing;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class ListingController extends ShopController
{
    public function __invoke(Listing $listing, Request $request, Analytics $analytics): View
    {
        abort_unless($listing->isOnStorefront(), 404);

        // A listing view is an event worth an id (docs/spec.md §4.1), so this
        // page is where a first-time visitor's row gets minted.
        $visitor = $this->knownVisitor();
        $now = $this->now();

        $analytics->recordEvent(AnalyticsEvent::forListing(
            AnalyticsEventName::ListingView,
            $listing->id,
            $visitor->id,
            $now,
            ListingViewCollapse::dedupeKey($listing->id, $visitor->id, $now),
        ));

        Story::for(StoryEvent::ListingView)->did('viewed a listing', [
            'listing_id' => $listing->id,
            'seller_id' => $listing->seller_id,
            'status' => $listing->status->value,
        ]);

        return view('shop.listing', ListingPagePresenter::forShop($listing, $visitor, $request));
    }
}
