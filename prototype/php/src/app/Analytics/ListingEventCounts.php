<?php

declare(strict_types=1);

namespace App\Analytics;

/**
 * One listing's tally from {@see AnalyticsReport::countsForListing()}.
 */
final readonly class ListingEventCounts
{
    public function __construct(
        public int $views,
        public int $favorites,
        public int $cartAdds,
    ) {}
}
