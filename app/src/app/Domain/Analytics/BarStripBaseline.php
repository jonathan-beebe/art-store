<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

/**
 * A signed series scaled by {@see BarStrip::baseline()}: the bars to render,
 * the pixel row from the strip's top edge where zero falls, and the strip's
 * own full height — `x-bar-strip` draws every bar against that zero row and
 * sizes its `<svg>` to that height.
 */
final readonly class BarStripBaseline
{
    /**
     * @param  list<BarStripBar>  $bars
     */
    public function __construct(
        public array $bars,
        public int $baselinePx,
        public int $heightPx,
    ) {}
}
