<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Which slice of a total-count-based list a viewer is looking at, and the
 * offset/limit a query needs to fetch it. `of()` clamps whatever a query
 * string asked for onto the nearest real page, the way every other admin
 * filter treats an out-of-range request as "give me the closest sane thing"
 * rather than a 400 — unlike the filter values `LogsQueryRequest` gates.
 */
final readonly class Page
{
    public function __construct(
        public int $number,
        public int $size,
        public int $totalCount,
        public int $count,
        public int $offset,
        public int $limit,
        public bool $isFirst,
        public bool $isLast,
        public int $previousNumber,
        public int $nextNumber,
    ) {}

    public static function of(?string $requested, int $size, int $totalCount): self
    {
        if ($size < 1) {
            throw new InvalidArgumentException("a page holds at least one item, got {$size}");
        }

        if ($totalCount < 0) {
            throw new InvalidArgumentException("a count cannot be negative, got {$totalCount}");
        }

        $count = max((int) ceil($totalCount / $size), 1);
        $asked = $requested !== null && is_numeric($requested) ? (int) $requested : 1;
        $number = self::clamp($asked, 1, $count);

        return new self(
            number: $number,
            size: $size,
            totalCount: $totalCount,
            count: $count,
            offset: ($number - 1) * $size,
            limit: $size,
            isFirst: $number === 1,
            isLast: $number === $count,
            previousNumber: $number - 1,
            nextNumber: $number + 1,
        );
    }

    private static function clamp(int $value, int $low, int $high): int
    {
        return min(max($value, $low), $high);
    }
}
