<?php

declare(strict_types=1);

namespace App\Domain\Store;

use DateTimeImmutable;
use DateTimeZone;

/**
 * A store page load writes an event without the buyer asking for one, so a
 * refresh would otherwise turn one visit into a dozen rows. The window is
 * the one a listing view already collapses to: the UTC hour.
 */
final class StoreViewCollapse
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
     * store, by the same customer (or every anonymous one alike), inside
     * the same UTC hour, collides on this string.
     */
    public static function dedupeKey(string $storeProfileId, ?string $customerId, DateTimeImmutable $now): string
    {
        return sprintf(
            'store:%s:customer:%s:hour:%s',
            $storeProfileId,
            $customerId ?? 'anonymous',
            self::windowStart($now)->format('Y-m-d\TH'),
        );
    }
}
