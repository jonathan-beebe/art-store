<?php

declare(strict_types=1);

namespace App\Domain\Listings;

final class ListingAvailability
{
    private function __construct() {}

    public static function isPurchasable(ListingStatus $status, int $quantity): bool
    {
        return $status === ListingStatus::ForSale && $quantity > 0;
    }
}
