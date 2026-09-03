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

    /** The UTC hour containing $now — the window a collapsed event shares with any other in it. */
    public static function windowStart(DateTimeImmutable $now): DateTimeImmutable
    {
        $utc = $now->setTimezone(new DateTimeZone('UTC'));

        return $utc->setTime((int) $utc->format('H'), 0, 0, 0);
    }

    /**
     * The key a collapsed view's insert dedupes on: every view of the same
     * listing, by the same customer (or every anonymous one alike), inside
     * the same UTC hour, collides on this string. A second write inside the
     * window is what the store's unique constraint on `dedupe_key` refuses.
     */
    public static function dedupeKey(string $listingId, ?string $customerId, DateTimeImmutable $now): string
    {
        return sprintf(
            'listing:%s:customer:%s:hour:%s',
            $listingId,
            $customerId ?? 'anonymous',
            self::windowStart($now)->format('Y-m-d\TH'),
        );
    }
}
