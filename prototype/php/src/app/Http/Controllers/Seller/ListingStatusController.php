<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Domain\Listings\ListingStatus;
use App\Http\Requests\Seller\ChangeListingStatusRequest;
use App\Logging\StoryEvent;
use App\Models\Listing;
use App\Support\Story;
use Illuminate\Http\RedirectResponse;

final class ListingStatusController extends SellerController
{
    public function __invoke(ChangeListingStatusRequest $request, Listing $listing): RedirectResponse
    {
        $next = $request->status();
        $from = $listing->status;

        // The form request admits only the transitions the current status
        // allows, so an illegal move is refused before it reaches here.
        $story = Story::for(StoryEvent::ListingTransition)->will('moving a listing to another status', [
            'listing_id' => $listing->id,
            'status_from' => $from->value,
            'status_to' => $next->value,
        ]);

        $listing->changeStatusTo($next);

        if ($next === ListingStatus::ForSale) {
            Story::for(StoryEvent::ListingPublish)->did('put the listing on the storefront', [
                'listing_id' => $listing->id,
                'slug' => $listing->slug,
            ]);
        }

        $story->did('moved the listing', [
            'listing_id' => $listing->id,
            'status_from' => $from->value,
            'status_to' => $next->value,
        ]);

        return redirect()
            ->route('seller.listings.index')
            ->with('status', "\"{$listing->title}\" is now ".lcfirst($next->label()).'.');
    }
}
