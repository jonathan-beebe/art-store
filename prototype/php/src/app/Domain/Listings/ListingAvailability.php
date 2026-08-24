<?php

declare(strict_types=1);

namespace App\Domain\Listings;

final class ListingAvailability
{
    private function __construct() {} // @codeCoverageIgnore

    public static function isPurchasable(ListingStatus $status, int $quantity): bool
    {
        return $status === ListingStatus::ForSale && $quantity > 0;
    }

    /**
     * A removal outranks the status it stands over: a listing off the
     * storefront for review or for good is off it whatever `status` still
     * says, because a removal changes nothing about the row underneath.
     */
    public static function isOnStorefront(ListingStatus $status, bool $hasActiveRemoval): bool
    {
        return $status->isOnStorefront() && ! $hasActiveRemoval;
    }

    /**
     * The transitions a seller may act on right now: the state machine's own
     * table, minus putting the listing back for sale while a removal stands.
     * The seller keeps every other move — archiving a removed listing, say —
     * because the removal says nothing about those.
     *
     * @return list<ListingStatus>
     */
    public static function availableTransitions(ListingStatus $status, bool $hasActiveRemoval): array
    {
        if (! $hasActiveRemoval) {
            return $status->transitions();
        }

        return array_values(array_filter(
            $status->transitions(),
            fn (ListingStatus $next): bool => $next !== ListingStatus::ForSale,
        ));
    }
}
