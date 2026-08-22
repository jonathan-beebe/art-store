<?php

namespace App\Domain\Listings;

use DomainException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ListingStockTest extends TestCase
{
    public function test_a_sale_leaves_the_listing_for_sale_while_stock_remains(): void
    {
        $stock = ListingStock::afterSale(3, ListingStatus::ForSale, 1);

        $this->assertSame(2, $stock->quantity);
        $this->assertSame(ListingStatus::ForSale, $stock->status);
    }

    public function test_a_sale_that_empties_the_stock_marks_the_listing_sold(): void
    {
        $stock = ListingStock::afterSale(1, ListingStatus::ForSale, 1);

        $this->assertSame(0, $stock->quantity);
        $this->assertSame(ListingStatus::Sold, $stock->status);
    }

    public function test_a_sale_rejects_more_than_the_listing_holds(): void
    {
        $this->expectException(DomainException::class);

        ListingStock::afterSale(1, ListingStatus::ForSale, 2);
    }

    public function test_a_sale_rejects_a_listing_that_is_not_for_sale(): void
    {
        $this->expectException(DomainException::class);

        ListingStock::afterSale(1, ListingStatus::Draft, 1);
    }

    public function test_a_sale_rejects_a_quantity_below_one(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ListingStock::afterSale(5, ListingStatus::ForSale, 0);
    }

    public function test_a_restock_puts_a_sold_listing_back_up_for_sale(): void
    {
        $stock = ListingStock::afterRestock(0, ListingStatus::Sold, 1);

        $this->assertSame(1, $stock->quantity);
        $this->assertSame(ListingStatus::ForSale, $stock->status);
    }

    public function test_a_restock_leaves_a_listing_that_never_sold_out_untouched(): void
    {
        $stock = ListingStock::afterRestock(2, ListingStatus::ForSale, 1);

        $this->assertSame(3, $stock->quantity);
        $this->assertSame(ListingStatus::ForSale, $stock->status);
    }

    public function test_a_restock_leaves_an_archived_listing_archived(): void
    {
        $this->assertSame(ListingStatus::Archived, ListingStock::afterRestock(0, ListingStatus::Archived, 1)->status);
    }

    public function test_a_restock_rejects_a_quantity_below_one(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ListingStock::afterRestock(0, ListingStatus::Sold, 0);
    }
}
