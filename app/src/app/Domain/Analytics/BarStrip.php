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
     * `x-bar-strip` to render.
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

    /**
     * A signed series scaled around a zero baseline: one shared scale, set
     * so the tallest positive value and the tallest negative magnitude
     * together fill `$maxPx`, so a swing of the same size reads the same
     * height whichever side of zero it falls on. A value of zero, or too
     * small a magnitude to clear {@see self::MIN_HEIGHT_PX}, still draws
     * that minimum, on the non-negative side. A series with nothing
     * negative in it puts the baseline on the strip's bottom edge, the
     * same picture {@see self::bars()} draws. Rounding a bar's own height
     * from the shared scale can overshoot the room its side of the
     * baseline has left; each bar is bounded by that budget after the
     * baseline itself is fixed, so a bar's own edge never crosses zero or
     * the strip's opposite edge.
     *
     * @param  list<int>  $values  signed
     * @param  list<string>  $tips  same length as `$values`, each bar's own tooltip
     */
    public static function baseline(array $values, array $tips, int $maxPx): BarStripBaseline
    {
        $tallestPositive = max([0, ...array_map(fn (int $value): int => max(0, $value), $values)]);
        $tallestNegative = max([0, ...array_map(fn (int $value): int => max(0, -$value), $values)]);
        $scale = $maxPx / max(1, $tallestPositive + $tallestNegative);

        if ($tallestNegative === 0) {
            $baselinePx = $maxPx;
        } else {
            $baselinePx = (int) round($tallestPositive * $scale);
            $hasNonNegative = array_filter($values, fn (int $value): bool => $value >= 0) !== [];
            $baselinePx = $hasNonNegative ? max($baselinePx, self::MIN_HEIGHT_PX) : $baselinePx;
            $baselinePx = min($baselinePx, $maxPx - self::MIN_HEIGHT_PX);
        }

        $aboveBudget = $baselinePx;
        $belowBudget = $maxPx - $baselinePx;

        $bars = array_map(
            fn (int $value, int $index): BarStripBar => new BarStripBar(
                height: max(self::MIN_HEIGHT_PX, min(
                    (int) round(abs($value) * $scale),
                    $value < 0 ? $belowBudget : $aboveBudget,
                )),
                tip: $tips[$index],
                negative: $value < 0,
            ),
            $values,
            array_keys($values),
        );

        return new BarStripBaseline($bars, $baselinePx, $maxPx);
    }
}
