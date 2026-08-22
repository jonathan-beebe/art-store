<?php

namespace App\Domain\Listings;

use PHPUnit\Framework\TestCase;

final class ListingAvailabilityTest extends TestCase
{
    public function test_a_for_sale_listing_with_stock_can_be_bought(): void
    {
        $this->assertTrue(ListingAvailability::isPurchasable(ListingStatus::ForSale, 1));
    }

    public function test_a_for_sale_listing_without_stock_cannot_be_bought(): void
    {
        $this->assertFalse(ListingAvailability::isPurchasable(ListingStatus::ForSale, 0));
    }

    public function test_for_sale_and_sold_listings_have_a_page(): void
    {
        $this->assertTrue(ListingAvailability::isOnStorefront(ListingStatus::ForSale));
        $this->assertTrue(ListingAvailability::isOnStorefront(ListingStatus::Sold));
    }

    public function test_a_draft_or_archived_listing_has_no_page(): void
    {
        $this->assertFalse(ListingAvailability::isOnStorefront(ListingStatus::Draft));
        $this->assertFalse(ListingAvailability::isOnStorefront(ListingStatus::Archived));
    }

    public function test_a_listing_that_is_not_for_sale_cannot_be_bought(): void
    {
        $this->assertFalse(ListingAvailability::isPurchasable(ListingStatus::Sold, 1));
        $this->assertFalse(ListingAvailability::isPurchasable(ListingStatus::Draft, 3));
        $this->assertFalse(ListingAvailability::isPurchasable(ListingStatus::Archived, 3));
    }
}
