<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

/**
 * One funnel step's conversion from the step immediately before it in
 * funnel order: a whole percentage a reader scans at a glance, and the
 * ratio it was rounded from for a caller doing its own math. The step at
 * the top of a funnel has nothing before it and carries no rate.
 */
final readonly class FunnelRate
{
    private function __construct(
        public string $text,
        public float $ratio,
    ) {}

    /**
     * `$ofPrevious` of zero has no ratio to compute against — the funnel
     * step it would describe carries no rate at all.
     */
    public static function of(int $current, int $ofPrevious): ?self
    {
        if ($ofPrevious === 0) {
            return null;
        }

        $ratio = $current / $ofPrevious;

        return new self(round($ratio * 100).'%', $ratio);
    }
}
