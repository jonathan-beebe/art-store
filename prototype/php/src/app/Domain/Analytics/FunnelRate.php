<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

/**
 * One funnel step's conversion from its own prerequisite step, which is
 * not always the step drawn immediately before it — cart adds' prerequisite
 * is listing views, not favorites. Carries a whole percentage a reader
 * scans at a glance, the ratio it was rounded from for a caller doing its
 * own math, and the prerequisite's own label for the "N% of {label}" a
 * page renders. The step at the top of a funnel has no prerequisite and
 * carries no rate.
 */
final readonly class FunnelRate
{
    private function __construct(
        public string $text,
        public float $ratio,
        public string $ofLabel,
    ) {}

    /**
     * `$ofPrevious` of zero has no ratio to compute against — the funnel
     * step it would describe carries no rate at all.
     */
    public static function of(int $current, int $ofPrevious, string $ofLabel): ?self
    {
        if ($ofPrevious === 0) {
            return null;
        }

        $ratio = $current / $ofPrevious;

        return new self(round($ratio * 100).'%', $ratio, $ofLabel);
    }
}
