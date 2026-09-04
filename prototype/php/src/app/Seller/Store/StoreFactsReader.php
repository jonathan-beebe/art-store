<?php

declare(strict_types=1);

namespace App\Seller\Store;

use App\Models\Listing;
use App\Models\StoreProfile;

/**
 * Reads {@see StoreFacts} for one store: how many pieces are for sale, and
 * how long the maker has been selling.
 */
final class StoreFactsReader
{
    private function __construct() {} // @codeCoverageIgnore

    public static function for(StoreProfile $profile): StoreFacts
    {
        $seller = $profile->loadMissing('seller')->seller;

        return new StoreFacts(
            // The sentence says "for sale", so the count is what a buyer
            // can still buy — a sold piece stays on the store page and is
            // left out of this number.
            pieceCount: Listing::query()
                ->where('seller_id', $profile->seller_id)
                ->forSale()
                ->count(),
            sellingSince: $seller?->email_verified_at?->toDateTimeImmutable()
                ?? $seller?->created_at?->toDateTimeImmutable(),
        );
    }
}
