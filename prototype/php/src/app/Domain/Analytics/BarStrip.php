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

    /**
     * A daily series as {@see BarStripBar} values: each count's scaled
     * height paired with a tooltip naming its day, ready for
     * `x-admin.analytics.bar-strip` to render.
     *
     * @param  list<int>  $counts
     * @param  list<string>  $dayLabels  same length as `$counts`, oldest first
     * @return list<BarStripBar>
     */
    public static function bars(array $counts, array $dayLabels, int $maxPx): array
    {
        $heights = self::heights($counts, $maxPx);

        return array_map(
            fn (int $height, int $index): BarStripBar => new BarStripBar(
                $height,
                AnalyticsRange::dayCaption($dayLabels[$index]).': '.number_format($counts[$index]),
            ),
            $heights,
            array_keys($heights),
        );
    }
}
