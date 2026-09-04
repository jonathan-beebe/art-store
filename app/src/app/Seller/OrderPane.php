<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Fulfillment\LaneFilter;

/**
 * The orders list pane as one lane leaves it: the rows in the window, and
 * how many the lane holds beyond it.
 */
final readonly class OrderPane
{
    /**
     * @param  list<OrderRow>  $rows
     */
    public function __construct(
        public LaneFilter $lane,
        public array $rows,
        public int $total,
    ) {}

    public function shown(): int
    {
        return count($this->rows);
    }

    public function isEmpty(): bool
    {
        return $this->rows === [];
    }
}
