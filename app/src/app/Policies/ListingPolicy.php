<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Listing;
use App\Models\Seller;
use Illuminate\Auth\Access\Response;

/**
 * A listing belongs to one seller. Another seller's listing answers "not
 * found" rather than "forbidden", so an id outside a seller's own catalogue
 * is never confirmed to exist.
 */
final class ListingPolicy
{
    public function view(Seller $seller, Listing $listing): Response
    {
        return $this->ownership($seller, $listing);
    }

    public function update(Seller $seller, Listing $listing): Response
    {
        return $this->ownership($seller, $listing);
    }

    private function ownership(Seller $seller, Listing $listing): Response
    {
        return $listing->seller_id === $seller->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
