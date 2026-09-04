<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Fulfillment\LaneFilter;

/**
 * One tab of the orders list pane: the lane it opens, the link, whether it
 * is the current one, and the number it wears — null on a tab that asks for
 * no work.
 */
final readonly class LaneTab
{
    public function __construct(
        public LaneFilter $lane,
        public string $href,
        public bool $active,
        public ?int $count = null,
    ) {}
}
