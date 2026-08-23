<?php

declare(strict_types=1);

namespace App\Domain\Listings;

use DomainException;
use PHPUnit\Framework\TestCase;

final class ListingStatusTest extends TestCase
{
    public function test_a_draft_may_be_put_up_for_sale_or_archived(): void
    {
        $this->assertSame(
            [ListingStatus::ForSale, ListingStatus::Archived],
            ListingStatus::Draft->transitions(),
        );
    }

    public function test_a_for_sale_listing_may_sell_out_or_be_archived(): void
    {
        $this->assertSame(
            [ListingStatus::Sold, ListingStatus::Archived],
            ListingStatus::ForSale->transitions(),
        );
    }

    public function test_a_sold_listing_returns_to_sale_when_stock_comes_back(): void
    {
        $this->assertSame([ListingStatus::ForSale], ListingStatus::Sold->transitions());
    }

    public function test_an_archived_listing_is_final(): void
    {
        $this->assertSame([], ListingStatus::Archived->transitions());
    }

    public function test_can_transition_to_agrees_with_the_transition_table(): void
    {
        foreach (ListingStatus::cases() as $from) {
            foreach (ListingStatus::cases() as $to) {
                $this->assertSame(
                    in_array($to, $from->transitions(), true),
                    $from->canTransitionTo($to),
                    "{$from->value} -> {$to->value}",
                );
            }
        }
    }

    public function test_transition_to_returns_the_next_status(): void
    {
        $this->assertSame(ListingStatus::ForSale, ListingStatus::Draft->transitionTo(ListingStatus::ForSale));
    }

    public function test_transition_to_rejects_a_move_outside_the_table(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('draft to sold');

        ListingStatus::Draft->transitionTo(ListingStatus::Sold);
    }
}
