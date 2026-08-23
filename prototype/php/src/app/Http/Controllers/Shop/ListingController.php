<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Actions\Listings\RecordListingEvent;
use App\Domain\Listings\ListingAvailability;
use App\Domain\Listings\ListingEventType;
use App\Models\Listing;
use Illuminate\Contracts\View\View;

final class ListingController extends ShopController
{
    public function __invoke(Listing $listing, RecordListingEvent $recordListingEvent): View
    {
        abort_unless($listing->status->isOnStorefront(), 404);

        $visitor = $this->visitor();
        $recordListingEvent($listing, $visitor->id, ListingEventType::View, $this->now());

        return $this->page('shop.listing', [
            'listing' => $listing->load('seller'),
            'isPurchasable' => ListingAvailability::isPurchasable($listing->status, $listing->quantity),
            'isFavorited' => $visitor->favorites()->where('listing_id', $listing->id)->exists(),
        ]);
    }
}
