<?php

declare(strict_types=1);

namespace App\Support\Configurator;

use App\Domain\Listings\ListingStatus;
use App\Models\Category;
use App\Models\Listing;

/**
 * Everything `seller.listings.edit` renders beyond the listing itself: the
 * category tree the form picks from, the current category's attribute
 * grants and the listing's existing values for them, the publish issues a
 * draft is held to, and each progressive-disclosure area's summary (or
 * `null` where the area is empty and the view shows its invitation instead).
 * Assembled once so {@see \App\Http\Controllers\Seller\ListingController}'s
 * happy path and {@see \App\Http\Controllers\Seller\ListingAttributeController}'s
 * rate-limited re-render build the exact same screen.
 */
final class ListingEditPageData
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
            'publishIssues' => $listing->status === ListingStatus::Draft ? $listing->publishIssues() : [],
            'choicesSummary' => ListingConfiguratorSummaries::choices($listing),
            'questionsSummary' => ListingConfiguratorSummaries::questions($listing),
            'discountsLine' => ListingConfiguratorSummaries::discountsLine($listing),
            'sectionsLine' => ListingConfiguratorSummaries::sectionsLine($listing),
            'piecesSummary' => ListingConfiguratorSummaries::pieces($listing),
        ];
    }
}
