<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

/**
 * A funnel step's count as a share of the funnel's first step — the width
 * every bar in the funnel draws at. A first step of zero has no share to
 * compute, so every step reads as an empty 0%; a real, nonzero share
 * floors at 2% so a thin sliver still reads as data.
 */
final class FunnelShare
{
    private const int MINIMUM_PERCENT = 2;

    private function __construct() {} // @codeCoverageIgnore

    public static function of(int $current, int $first): int
    {
        if ($first === 0) {
            return 0;
        }

        return max(self::MINIMUM_PERCENT, (int) round(($current / $first) * 100));
    }
}
