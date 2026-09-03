<?php

declare(strict_types=1);

namespace App\Analytics\Admin;

use App\Domain\Analytics\RangeChange;

/**
 * One number {@see ChannelRow} carries for one channel — visitors, views,
 * cart adds, orders placed, or orders paid — for the range and for the
 * range before it, plus the {@see RangeChange} between them.
 */
final readonly class ChannelMetric
{
    private function __construct(
        public int $current,
        public int $previous,
        public RangeChange $change,
    ) {}

    public static function of(int $current, int $previous): self
    {
        return new self($current, $previous, RangeChange::between($current, $previous));
    }
}
