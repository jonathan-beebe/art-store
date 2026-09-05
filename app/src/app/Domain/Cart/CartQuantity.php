<?php

declare(strict_types=1);

namespace App\Domain\Cart;

use App\Domain\DomainRuleViolation;
use App\Domain\Listings\ListingAvailability;
use App\Domain\Listings\ListingStatus;
use InvalidArgumentException;

final class CartQuantity
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * A removal is refused here alongside the status and the stock, because
     * a piece taken off the storefront mid-request is one nothing may put in
     * a cart — the same answer `/art/{slug}` gives by then, and the answer
     * checkout gives to a line that is already in one.
     */
    public static function withinStock(int $requested, ?int $available, ListingStatus $status, bool $hasActiveRemoval): int
    {
        if ($requested < 1) {
            throw new InvalidArgumentException("A cart holds at least one of a listing, got {$requested}.");
        }

        if ($hasActiveRemoval || ! ListingAvailability::isPurchasable($status, $available)) {
            throw new DomainRuleViolation('That listing is no longer for sale.');
        }

        return $available === null ? $requested : min($requested, $available);
    }
}
