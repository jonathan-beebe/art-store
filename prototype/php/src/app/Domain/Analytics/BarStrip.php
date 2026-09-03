<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

/**
 * Scales a daily series onto pixel bar heights: the tallest count fills
 * `$maxPx`, every other count is proportional to it, and nothing ever
 * renders shorter than 2px — a real zero and a rounding-error sliver both
 * still read as a bar.
 */
final class BarStrip
{
    private const int MIN_HEIGHT_PX = 2;

    private function __construct() {} // @codeCoverageIgnore

    /**
     * @param  list<int>  $counts
     * @return list<int>
     */
    public static function heights(array $counts, int $maxPx): array
    {
        $tallest = max([1, ...$counts]);

        return array_map(
            fn (int $count): int => max(self::MIN_HEIGHT_PX, (int) round(($count / $tallest) * $maxPx)),
            $counts,
        );
    }
}
