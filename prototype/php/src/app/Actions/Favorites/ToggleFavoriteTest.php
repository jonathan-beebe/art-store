<?php

declare(strict_types=1);

namespace App\Actions\Favorites;

use App\Domain\Favorites\FavoriteChange;
use App\Domain\Listings\ListingEventType;
use App\Models\Favorite;
use App\Models\ListingEvent;

it('adds a favorite and records the event', function (): void {
    $customer = $this->anonymousCustomer();
    $listing = $this->listing($this->seller());

    $change = app(ToggleFavorite::class)($customer, $listing, $this->moment('2026-08-20 08:00:00'));

    expect($change)->toBe(FavoriteChange::Added)
        ->and(Favorite::sole()->listing_id)->toBe($listing->id)
        ->and(ListingEvent::sole()->type)->toBe(ListingEventType::Favorite);
});

it('removes a favorite and records the event', function (): void {
    $customer = $this->anonymousCustomer();
    $listing = $this->listing($this->seller());
    $toggle = app(ToggleFavorite::class);
    $toggle($customer, $listing, $this->moment('2026-08-20 08:00:00'));

    $change = $toggle($customer, $listing, $this->moment('2026-08-20 08:05:00'));

    expect($change)->toBe(FavoriteChange::Removed)
        ->and(Favorite::count())->toBe(0)
        ->and(ListingEvent::orderByDesc('id')->firstOrFail()->type)->toBe(ListingEventType::Unfavorite);
});
