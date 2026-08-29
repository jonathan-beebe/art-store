<?php

declare(strict_types=1);

namespace App\Domain\Listings;

final class ListingAvailability
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * A null quantity is made-to-order — no fixed count, so nothing here
     * caps it; only the status gates it.
     */
    public static function isPurchasable(ListingStatus $status, ?int $quantity): bool
    {
        return $status === ListingStatus::ForSale && ($quantity === null || $quantity > 0);
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
     * The status half of {@see isOnStorefront}, as a set a `where` clause can
     * hold: a page that turns many rows into visible listings asks the
     * question of the whole set at once rather than once per row.
     *
     * @return list<ListingStatus>
     */
    public static function storefrontStatuses(): array
    {
        return array_values(array_filter(
            ListingStatus::cases(),
            fn (ListingStatus $status): bool => $status->isOnStorefront(),
        ));
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
