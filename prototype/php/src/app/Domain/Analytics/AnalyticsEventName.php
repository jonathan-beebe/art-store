<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

/**
 * The closed vocabulary the analytics store accepts — a reader greps this
 * file for every name {@see \App\Analytics\Analytics::recordEvent()} can be
 * called with.
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

    /** The plural, descriptive form the admin analytics entry and event
     * pages show, where {@see label()}'s single-word form reads too
     * terse for a table column or a page heading. */
    public function pluralLabel(): string
    {
        return match ($this) {
            self::ListingView => 'Listing views',
            self::ListingFavorite => 'Favorites',
            self::ListingUnfavorite => 'Unfavorites',
            self::ListingCartAdd => 'Cart adds',
        };
    }

    /** The sentence verb an event feed row reads: "{actor} {verb} {listing}". */
    public function verb(): string
    {
        return match ($this) {
            self::ListingView => 'viewed',
            self::ListingFavorite => 'favorited',
            self::ListingUnfavorite => 'unfavorited',
            self::ListingCartAdd => 'added to cart',
        };
    }

    /** The `<path d="…">` an event feed row's icon draws — a 24x24 outline,
     * the same shape the admin analytics entry and event pages already use. */
    public function iconPath(): string
    {
        return match ($this) {
            self::ListingView => 'M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178zM15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z',
            self::ListingFavorite => 'M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z',
            self::ListingUnfavorite => 'M3 3l18 18M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s2.2-1.17 4.6-3.3',
            self::ListingCartAdd => 'M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0z',
        };
    }
}
