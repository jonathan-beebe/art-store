<?php

declare(strict_types=1);

namespace App\Domain\Listings;

use DateTimeImmutable;
use DateTimeZone;

/**
 * A `view` is the one listing event a page load writes without a customer
 * asking for it, so a refresh would otherwise turn one visit into a dozen
 * rows. `favorite`, `unfavorite`, and `cart_add` are each a deliberate click
 * and are recorded every time.
 */
final class ListingViewCollapse
{
    private function __construct() {} // @codeCoverageIgnore

    public static function collapsesHourly(ListingEventType $type): bool
    {
        return match ($type) {
            ListingEventType::View => true,
            ListingEventType::Favorite, ListingEventType::Unfavorite, ListingEventType::CartAdd => false,
        };
    }

    /** The UTC hour containing $now — the window a collapsed event shares with any other in it. */
    public static function windowStart(DateTimeImmutable $now): DateTimeImmutable
    {
        $utc = $now->setTimezone(new DateTimeZone('UTC'));

        return $utc->setTime((int) $utc->format('H'), 0, 0, 0);
    }
}
