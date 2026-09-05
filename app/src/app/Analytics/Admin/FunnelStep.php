<?php

declare(strict_types=1);

namespace App\Analytics\Admin;

use App\Domain\Analytics\FunnelRate;
use App\Domain\Analytics\RangeChange;

/**
 * One step of {@see FunnelView}: its count for the range, the count for
 * the range before it, its conversion from its own prerequisite step
 * ({@see FunnelRate}, null on the funnel's first step, which has none),
 * and its count as a share of the funnel's first step this range and the
 * range before ({@see \App\Domain\Analytics\FunnelShare}). `$isLargestDrop`
 * is true on the one step whose rate is the lowest among every step that
 * carries one. `$note` and `$side` are optional lines a step's own kind of
 * event carries — the paid step's cancelled count, the viewed step's
 * favorited count — never both on the same step.
 */
final readonly class FunnelStep
{
    public function __construct(
        public string $key,
        public string $label,
        public int $current,
        public int $previous,
        public RangeChange $change,
        public ?FunnelRate $rate,
        public int $shareOfFirst,
        public int $previousShareOfFirst,
        public bool $isLargestDrop,
        public ?string $note = null,
        public ?string $side = null,
    ) {}
}
