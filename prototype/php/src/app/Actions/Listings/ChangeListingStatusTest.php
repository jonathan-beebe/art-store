<?php

namespace App\Actions\Listings;

use App\Domain\Listings\ListingStatus;
use DomainException;
use Tests\CommerceTestCase;

final class ChangeListingStatusTest extends CommerceTestCase
{
    public function test_it_puts_a_draft_up_for_sale(): void
    {
        $listing = $this->listing($this->seller(), ['status' => ListingStatus::Draft]);

        ($this->changeListingStatus())($listing, ListingStatus::ForSale);

        $this->assertSame(ListingStatus::ForSale, $listing->fresh()->status);
    }

    public function test_it_archives_a_listing_that_is_for_sale(): void
    {
        $listing = $this->listing($this->seller(), ['status' => ListingStatus::ForSale]);

        ($this->changeListingStatus())($listing, ListingStatus::Archived);

        $this->assertSame(ListingStatus::Archived, $listing->fresh()->status);
    }

    public function test_it_refuses_a_transition_the_lifecycle_does_not_allow(): void
    {
        $listing = $this->listing($this->seller(), ['status' => ListingStatus::Archived]);

        $this->expectException(DomainException::class);

        ($this->changeListingStatus())($listing, ListingStatus::ForSale);
    }

    public function test_it_leaves_the_row_alone_when_the_transition_is_refused(): void
    {
        $listing = $this->listing($this->seller(), ['status' => ListingStatus::Draft]);

        try {
            ($this->changeListingStatus())($listing, ListingStatus::Sold);
        } catch (DomainException) {
            // The assertion below is the point; the throw is asserted above.
        }

        $this->assertSame(ListingStatus::Draft, $listing->fresh()->status);
    }

    private function changeListingStatus(): ChangeListingStatus
    {
        return app(ChangeListingStatus::class);
    }
}
