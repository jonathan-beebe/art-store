<?php

declare(strict_types=1);

namespace App\Domain\Cart;

use App\Domain\DomainRuleViolation;
use App\Domain\Listings\ListingAvailability;
use App\Domain\Listings\ListingStatus;
use InvalidArgumentException;

final class CartQuantity
{
    private function __construct() {}

    public static function withinStock(int $requested, int $available, ListingStatus $status): int
    {
        if ($requested < 1) {
            throw new InvalidArgumentException("A cart holds at least one of a listing, got {$requested}.");
        }

        if (! ListingAvailability::isPurchasable($status, $available)) {
            throw new DomainRuleViolation('That listing is no longer for sale.');
        }

        return min($requested, $available);
    }
}
