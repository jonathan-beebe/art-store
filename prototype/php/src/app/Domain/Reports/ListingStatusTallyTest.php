<?php

namespace App\Domain\Reports;

use App\Domain\Listings\ListingStatus;
use PHPUnit\Framework\TestCase;

final class ListingStatusTallyTest extends TestCase
{
    public function test_it_returns_every_status_in_lifecycle_order(): void
    {
        $tally = ListingStatusTally::from([]);

        $this->assertSame(
            [ListingStatus::Draft, ListingStatus::ForSale, ListingStatus::Sold, ListingStatus::Archived],
            array_map(fn (ListingStatusCount $row): ListingStatus => $row->status, $tally),
        );
    }

    public function test_it_reads_the_count_recorded_for_a_status(): void
    {
        $tally = ListingStatusTally::from([ListingStatus::ForSale->value => 3]);

        $this->assertSame(3, $tally[1]->count);
    }

    public function test_it_counts_a_status_with_no_listings_as_zero(): void
    {
        $tally = ListingStatusTally::from([ListingStatus::ForSale->value => 3]);

        $this->assertSame(0, $tally[0]->count);
    }

    public function test_it_totals_every_status(): void
    {
        $tally = ListingStatusTally::from([
            ListingStatus::ForSale->value => 3,
            ListingStatus::Sold->value => 2,
        ]);

        $this->assertSame(5, ListingStatusTally::total($tally));
    }
}
