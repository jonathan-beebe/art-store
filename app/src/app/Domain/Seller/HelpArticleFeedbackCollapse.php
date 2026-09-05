<?php

declare(strict_types=1);

namespace App\Domain\Seller;

use App\Domain\Analytics\AnalyticsEventName;
use DateTimeImmutable;
use DateTimeZone;

/**
 * A seller can press "Did this answer it?" more than once on the same
 * article in one sitting. The window is the UTC day: a feedback click is
 * a deliberate answer. `$name` is folded into the key, so a Yes and a
 * later No the same day each get their own row.
 */
final class HelpArticleFeedbackCollapse
{
    private function __construct() {} // @codeCoverageIgnore

    /** The key a collapsed feedback click's insert dedupes on: every click
     * of the same kind, on the same article, by the same seller, inside
     * the same UTC day, collides on this string. */
    public static function dedupeKey(AnalyticsEventName $name, string $articleSlug, string $sellerId, DateTimeImmutable $now): string
    {
        return sprintf(
            'help:%s:article:%s:seller:%s:day:%s',
            $name->value,
            $articleSlug,
            $sellerId,
            $now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d'),
        );
    }
}
