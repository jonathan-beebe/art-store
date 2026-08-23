<?php

declare(strict_types=1);

namespace App\Domain\Favorites;

use App\Domain\Listings\ListingEventType;

it('adds a listing that is not favorited', function (): void {
    $change = FavoriteChange::fromCurrentState(false);

    expect($change)->toBe(FavoriteChange::Added)
        ->and($change->listingEvent())->toBe(ListingEventType::Favorite);
});

it('removes a favorited listing', function (): void {
    $change = FavoriteChange::fromCurrentState(true);

    expect($change)->toBe(FavoriteChange::Removed)
        ->and($change->listingEvent())->toBe(ListingEventType::Unfavorite);
});
