<?php

declare(strict_types=1);

namespace App\Actions\Favorites;

use App\Domain\Favorites\FavoriteChange;
use App\Domain\Listings\ListingEventType;
use App\Models\Favorite;
use App\Models\ListingEvent;
use Tests\CommerceTestCase;

final class ToggleFavoriteTest extends CommerceTestCase
{
    public function test_it_adds_a_favorite_and_records_the_event(): void
    {
        $customer = $this->anonymousCustomer();
        $listing = $this->listing($this->seller());

        $change = app(ToggleFavorite::class)($customer, $listing, $this->moment('2026-08-20 08:00:00'));

        $this->assertSame(FavoriteChange::Added, $change);
        $this->assertSame($listing->id, Favorite::sole()->listing_id);
        $this->assertSame(ListingEventType::Favorite, ListingEvent::sole()->type);
    }

    public function test_it_removes_a_favorite_and_records_the_event(): void
    {
        $customer = $this->anonymousCustomer();
        $listing = $this->listing($this->seller());
        $toggle = app(ToggleFavorite::class);
        $toggle($customer, $listing, $this->moment('2026-08-20 08:00:00'));

        $change = $toggle($customer, $listing, $this->moment('2026-08-20 08:05:00'));

        $this->assertSame(FavoriteChange::Removed, $change);
        $this->assertSame(0, Favorite::count());
        $this->assertSame(ListingEventType::Unfavorite, ListingEvent::orderByDesc('id')->first()->type);
    }
}
