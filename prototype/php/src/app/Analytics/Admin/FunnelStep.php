<?php

declare(strict_types=1);

namespace App\Analytics\Admin;

use App\Domain\Analytics\FunnelRate;
use App\Domain\Analytics\RangeChange;

/**
 * One step of {@see FunnelView}: its count for the range, the count for
 * the range before it, and its conversion from the step immediately
 * before it in the funnel — null on the funnel's first step, which has
 * none. Only the paid step carries a `$note` — the cancelled count the
 * funnel keeps honest without turning it into a step of its own.
 */
final readonly class FunnelStep
{
    public function __construct(
        public string $label,
        public int $current,
        public int $previous,
        public RangeChange $change,
        public ?FunnelRate $rate,
        public ?string $note = null,
    ) {}
}
