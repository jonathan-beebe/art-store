<?php

namespace App\Domain\Listings;

enum ListingEventType: string
{
    case View = 'view';
    case Favorite = 'favorite';
    case Unfavorite = 'unfavorite';
    case CartAdd = 'cart_add';
}
