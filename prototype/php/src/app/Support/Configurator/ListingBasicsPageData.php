<?php

declare(strict_types=1);

namespace App\Support\Configurator;

use App\Models\Category;
use App\Models\Listing;

/**
 * Everything the "Your item" detail screen renders beyond the listing
 * itself: the category tree its select picks from, the current category's
 * attribute grants and the listing's existing values for them, and whether
 * this listing still prices and stocks itself (the screen shows Price and
 * "How many you have" only then — {@see Listing::hasOwnPriceAndStock()}).
 * Assembled once so {@see \App\Http\Controllers\Seller\ListingBasicsController}'s
 * happy path and the rate-limited re-renders on
 * {@see \App\Http\Controllers\Seller\ListingController} and
 * {@see \App\Http\Controllers\Seller\ListingAttributeController} build the
 * exact same screen.
 */
final class ListingBasicsPageData
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * @return array<string, mixed>
     */
    public static function for(Listing $listing): array
    {
        return [
            'listing' => $listing,
            'categories' => Category::orderBy('path')->get(),
            'attributeGrants' => ListingAttributeSection::grants($listing),
            'listingAttributeSelections' => ListingAttributeSection::selections($listing),
            'hasOwnPriceAndStock' => $listing->hasOwnPriceAndStock(),
        ];
    }
}
