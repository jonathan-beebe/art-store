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
    case CheckoutOpen = 'checkout.open';
    case OrderPlace = 'order.place';
    case OrderPay = 'order.pay';
    case OrderCancel = 'order.cancel';

    public function label(): string
    {
        return match ($this) {
            self::ListingView => 'View',
            self::ListingFavorite => 'Favorite',
            self::ListingUnfavorite => 'Unfavorite',
            self::ListingCartAdd => 'Cart add',
            self::CheckoutOpen => 'Checkout opened',
            self::OrderPlace => 'Order placed',
            self::OrderPay => 'Order paid',
            self::OrderCancel => 'Order cancelled',
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
            self::CheckoutOpen => 'Checkouts opened',
            self::OrderPlace => 'Orders placed',
            self::OrderPay => 'Orders paid',
            self::OrderCancel => 'Orders cancelled',
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
            self::CheckoutOpen => 'opened checkout',
            self::OrderPlace => 'placed an order',
            self::OrderPay => 'paid an order',
            self::OrderCancel => 'cancelled an order',
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
            self::CheckoutOpen => 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z',
            self::OrderPlace => 'M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z',
            self::OrderPay => 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z',
            self::OrderCancel => 'M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        };
    }
}
