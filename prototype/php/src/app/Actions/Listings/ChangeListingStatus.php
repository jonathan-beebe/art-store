<?php

namespace App\Actions\Listings;

use App\Domain\Listings\ListingStatus;
use App\Models\Listing;

final class ChangeListingStatus
{
    public function __invoke(Listing $listing, ListingStatus $next): Listing
    {
        $listing->update(['status' => $listing->status->transitionTo($next)]);

        return $listing;
    }
}
