<?php

namespace App\Domain\Listings;

final class ListingAvailability
{
    /**
     * A sold listing keeps its page so the links a buyer already followed
     * still lead somewhere; a draft or archived one was never public.
     */
    public static function isOnStorefront(ListingStatus $status): bool
    {
        return $status === ListingStatus::ForSale || $status === ListingStatus::Sold;
    }

    public static function isPurchasable(ListingStatus $status, int $quantity): bool
    {
        return $status === ListingStatus::ForSale && $quantity > 0;
    }
}
