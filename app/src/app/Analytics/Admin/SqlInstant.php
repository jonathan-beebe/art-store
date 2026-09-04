<?php

declare(strict_types=1);

namespace App\Analytics\Admin;

use DateTimeImmutable;

/**
 * A moment formatted the way `analytics_events.occurred_at` compares
 * against it — every query class under `App\Analytics\Admin` that bounds a
 * range by that column formats its edges through this.
 */
final class SqlInstant
{
    private function __construct() {} // @codeCoverageIgnore

    public static function format(DateTimeImmutable $moment): string
    {
        return $moment->format('Y-m-d H:i:s');
    }
}
