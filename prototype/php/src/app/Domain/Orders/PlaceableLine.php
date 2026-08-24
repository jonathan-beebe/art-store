<?php

declare(strict_types=1);

namespace App\Domain\Orders;

use App\Domain\Listings\ListingStatus;

/**
 * A cart or order line as placement judges it: the quantity asked for,
 * against what the listing behind it allows right now. `hasActiveRemoval`
 * waits on FEAT-024 to wire an admin listing removal in — every caller
 * passes `false` until then, so a removed listing reads as whatever its
 * ordinary status says instead.
 */
final readonly class PlaceableLine
{
    public function __construct(
        public string $listingId,
        public string $title,
        public ListingStatus $status,
        public int $availableQuantity,
        public int $quantity,
        public bool $hasActiveRemoval,
    ) {}
}
