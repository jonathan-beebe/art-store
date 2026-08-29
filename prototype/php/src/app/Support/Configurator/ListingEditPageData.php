<?php

declare(strict_types=1);

namespace App\Support\Configurator;

use App\Domain\Listings\ListingStatus;
use App\Models\Listing;

/**
 * Everything `seller.listings.edit` — the row-based hub — renders beyond the
 * listing itself: the publish issues a draft is held to, and each row's
 * summary (or `null` where the area is empty and the view shows its
 * invitation instead). The "Your item" and Images rows always have
 * something to show, so their summaries are never null. Assembled once so
 * {@see \App\Http\Controllers\Seller\ListingController}'s happy path and its
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
            'publishIssues' => $listing->status === ListingStatus::Draft ? $listing->publishIssues() : [],
            'basics' => ListingConfiguratorSummaries::basics($listing),
            'imagesSummary' => ListingConfiguratorSummaries::images($listing),
            'choicesSummary' => ListingConfiguratorSummaries::choices($listing),
            'questionsSummary' => ListingConfiguratorSummaries::questions($listing),
            'discountsLine' => ListingConfiguratorSummaries::discountsLine($listing),
            'sectionsLine' => ListingConfiguratorSummaries::sectionsLine($listing),
            'piecesSummary' => ListingConfiguratorSummaries::pieces($listing),
        ];
    }
}
