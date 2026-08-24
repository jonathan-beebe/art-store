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

        return Story::for(StoryEvent::ListingTransition)->tell('moving a listing to another status', [
            'listing_id' => $listing->id,
            'status_from' => $from->value,
            'status_to' => $next->value,
        ], function (Story $story) use ($listing, $from, $next): RedirectResponse {
            // The form request admits only the transitions the status held
            // when it ran. A status that moved between then and here is
            // refused by the core, and the refusal ends this story.
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
        });
    }
}
