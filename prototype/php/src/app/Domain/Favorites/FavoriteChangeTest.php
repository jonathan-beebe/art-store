<?php

namespace App\Domain\Favorites;

use App\Domain\Listings\ListingEventType;
use PHPUnit\Framework\TestCase;

final class FavoriteChangeTest extends TestCase
{
    public function test_a_listing_that_is_not_favorited_gets_added(): void
    {
        $change = FavoriteChange::fromCurrentState(false);

        $this->assertSame(FavoriteChange::Added, $change);
        $this->assertSame(ListingEventType::Favorite, $change->listingEvent());
    }

    public function test_a_favorited_listing_gets_removed(): void
    {
        $change = FavoriteChange::fromCurrentState(true);

        $this->assertSame(FavoriteChange::Removed, $change);
        $this->assertSame(ListingEventType::Unfavorite, $change->listingEvent());
    }
}
