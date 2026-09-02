<?php

declare(strict_types=1);

namespace App\Analytics;

/**
 * The closed vocabulary the analytics store accepts — a reader greps this
 * file for every name {@see Analytics::recordEvent()} can be called with.
 */
enum AnalyticsEventName: string
{
    case ListingView = 'listing.view';
    case ListingFavorite = 'listing.favorite';
    case ListingUnfavorite = 'listing.unfavorite';
    case ListingCartAdd = 'listing.cart_add';

    public function label(): string
    {
        return match ($this) {
            self::ListingView => 'View',
            self::ListingFavorite => 'Favorite',
            self::ListingUnfavorite => 'Unfavorite',
            self::ListingCartAdd => 'Cart add',
        };
    }
}
