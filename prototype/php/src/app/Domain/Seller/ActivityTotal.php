<?php

declare(strict_types=1);

namespace App\Domain\Seller;

use App\Domain\Analytics\RangeChange;

/**
 * One of the four figures over the dashboard's listing activity: what a
 * range counted, and how that reads against the range before it.
 */
final readonly class ActivityTotal
{
    private function __construct(
        public string $label,
        public int $count,
        public RangeChange $change,
    ) {}

    public static function between(string $label, int $count, int $previous): self
    {
        return new self($label, $count, RangeChange::between($count, $previous));
    }

    /** The figure with its thousands separators, the way every count on the page reads. */
    public function figure(): string
    {
        return number_format($this->count);
    }
}
