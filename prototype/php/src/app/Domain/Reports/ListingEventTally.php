<?php

declare(strict_types=1);

namespace App\Domain\Reports;

use App\Domain\Listings\ListingEventType;

/**
 * How much the storefront looked, favorited, and added to a cart, across
 * every listing — `/admin/stats`'s listing-event tally, zero-filled for a
 * type nobody has triggered yet.
 */
final class ListingEventTally
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * @param  array<string, int>  $countsByType  event type value => count
     * @return list<ListingEventCount>
     */
    public static function from(array $countsByType): array
    {
        return array_map(
            fn (ListingEventType $type): ListingEventCount => ListingEventCount::of(
                $type,
                $countsByType[$type->value] ?? 0,
            ),
            ListingEventType::cases(),
        );
    }
}
