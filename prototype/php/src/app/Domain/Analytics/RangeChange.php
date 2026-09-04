<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

/**
 * How a range's count compares with the range before it: a signed
 * percentage a reader scans at a glance, and the {@see ChangeDirection}
 * that picks the up/down/flat color it renders in. A move under 0.5%
 * reads as flat: a percentage that small is noise on any count worth
 * comparing.
 */
final readonly class RangeChange
{
    private const float FLAT_THRESHOLD_PERCENT = 0.5;

    private function __construct(
        public string $text,
        public ChangeDirection $direction,
    ) {}

    /**
     * `$previous` of zero has no percentage to compute against.
     * `$current` also zero reads as empty — nothing happened in either
     * range. A nonzero `$current` reads "new": it takes the flat color,
     * since a fresh count is neither up nor down.
     */
    public static function between(int $current, int $previous): self
    {
        if ($previous === 0) {
            return $current === 0
                ? new self('', ChangeDirection::Flat)
                : new self('new', ChangeDirection::Flat);
        }

        // Divides by the previous range's size, not its sign — a negative
        // previous (a period net of a refund) still reads the change
        // toward zero as an improvement.
        $percent = (($current - $previous) / abs($previous)) * 100;

        if (abs($percent) < self::FLAT_THRESHOLD_PERCENT) {
            return new self('0.0%', ChangeDirection::Flat);
        }

        $sign = $percent >= 0 ? '+' : '−';
        $direction = $percent >= 0 ? ChangeDirection::Up : ChangeDirection::Down;

        return new self($sign.number_format(abs($percent), 1).'%', $direction);
    }
}
