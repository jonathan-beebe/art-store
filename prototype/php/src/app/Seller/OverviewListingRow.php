<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Analytics\BarStripBar;
use App\Domain\Seller\ListingTableRow;

/**
 * One listing under the dashboard's activity table: the row the listings
 * table already builds, the units it sold inside the range, its daily view
 * strip, and the page it opens.
 */
final readonly class OverviewListingRow
{
    /**
     * @param  list<BarStripBar>  $strip
     */
    public function __construct(
        public ListingTableRow $listing,
        public int $sold,
        public array $strip,
        public string $href,
    ) {}
}
