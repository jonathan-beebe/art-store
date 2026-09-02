<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Analytics\Analytics;
use App\Analytics\AnalyticsEvent;
use App\Analytics\AnalyticsEventName;
use App\Domain\Listings\ListingViewCollapse;
use App\Logging\StoryEvent;
use App\Models\Listing;
use App\Support\Configurator\ListingPagePresenter;
use App\Support\Story;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class ListingController extends ShopController
{
    public function __invoke(Listing $listing, Request $request, Analytics $analytics): View
    {
        abort_unless($listing->isOnStorefront(), 404);

        $visitor = $this->visitor();
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
