<?php

declare(strict_types=1);

namespace App\Domain\Seller;

/**
 * A daily series scaled onto one SVG polyline: the points attribute the
 * line draws from, and the last point on its own, which the tile marks
 * with a dot. The line spans the full width and keeps a two-pixel inset
 * top and bottom, so the tallest and the flattest day both sit inside the
 * box. {@see \App\Domain\Analytics\BarStrip} is the same idea in bars.
 */
final readonly class Sparkline
{
    /** The clearance the line keeps from the top and bottom edges. */
    private const int INSET_PX = 2;

    private const int DECIMALS = 1;

    private function __construct(
        public string $points,
        public string $endX,
        public string $endY,
    ) {}

    /**
     * `$counts` oldest first, one entry per day. A series with fewer than
     * two entries has no slope to draw and comes back as a flat line
     * across the box.
     *
     * @param  list<int>  $counts
     */
    public static function of(array $counts, int $width, int $height): self
    {
        $floor = (float) ($height - self::INSET_PX);
        $span = (float) ($height - (self::INSET_PX * 2));

        if (count($counts) < 2) {
            return self::flat($width, $floor);
        }

        $lowest = min($counts);
        $reach = max(1, max($counts) - $lowest);

        $points = [];

        foreach ($counts as $index => $count) {
            $x = ($index / (count($counts) - 1)) * $width;
            $y = $floor - (($count - $lowest) / $reach) * $span;
            $points[] = self::round($x).','.self::round($y);
        }

        [$endX, $endY] = explode(',', $points[count($points) - 1]);

        return new self(implode(' ', $points), $endX, $endY);
    }

    private static function flat(int $width, float $floor): self
    {
        $y = self::round($floor);
        $end = self::round((float) $width);

        return new self(self::round(0.0).','.$y.' '.$end.','.$y, $end, $y);
    }

    private static function round(float $value): string
    {
        return number_format($value, self::DECIMALS, '.', '');
    }
}
