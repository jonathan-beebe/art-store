<?php

declare(strict_types=1);

namespace App\Domain\Reports;

use App\Analytics\AnalyticsEventName;

/**
 * How much the storefront looked, favorited, and added to a cart, across
 * every listing — `/admin/stats`'s listing-event tally, zero-filled for a
 * name nobody has triggered yet.
 */
final class ListingEventTally
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * @param  array<string, int>  $countsByName  event name value => count
     * @return list<ListingEventCount>
     */
    public static function from(array $countsByName): array
    {
        return array_map(
            fn (AnalyticsEventName $name): ListingEventCount => ListingEventCount::of(
                $name,
                $countsByName[$name->value] ?? 0,
            ),
            AnalyticsEventName::cases(),
        );
    }
}
