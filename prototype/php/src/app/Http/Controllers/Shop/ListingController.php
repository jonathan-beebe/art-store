<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Actions\Listings\RecordListingEvent;
use App\Domain\Listings\ListingEventType;
use App\Logging\StoryEvent;
use App\Models\Listing;
use App\Support\Configurator\ListingPagePresenter;
use App\Support\Story;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class ListingController extends ShopController
{
    public function __invoke(Listing $listing, Request $request, RecordListingEvent $recordListingEvent): View
    {
        abort_unless($listing->isOnStorefront(), 404);

        $visitor = $this->visitor();
        $event = $recordListingEvent($listing, $visitor->id, ListingEventType::View, $this->now());

        // RecordListingEvent returns null both for a repeat view within the
        // hour and for a failed analytics write already logged by
        // AnalyticsWriteGuard, so the story records either case as a
        // refusal.
        $story = Story::for(StoryEvent::ListingView);
        $data = ['listing_id' => $listing->id, 'seller_id' => $listing->seller_id];

        $event === null
            ? $story->refused('collapsed a repeat view into the hour already recorded', $data)
            : $story->did('viewed a listing', [...$data, 'status' => $listing->status->value]);

        return view('shop.listing', ListingPagePresenter::forShop($listing, $visitor, $request));
    }
}
