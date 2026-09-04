<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

/**
 * A signed series scaled by {@see BarStrip::baseline()}: the bars to render
 * and the pixel row, from the strip's top edge, where zero falls. `x-bar-strip`
 * draws every bar against that row instead of the strip's bottom edge.
 */
final readonly class BarStripBaseline
{
    /**
     * @param  list<BarStripBar>  $bars
     */
    public function __construct(
        public array $bars,
        public int $baselinePx,
    ) {}
}
