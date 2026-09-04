<?php

declare(strict_types=1);

namespace App\Domain\Orders;

/**
 * One line placement refused, named so every reason reaches the shopper
 * beside the piece it is about rather than as a single message covering the
 * whole cart.
 */
final readonly class BlockedLine
{
    public function __construct(
        public string $listingId,
        public string $title,
        public UnavailableReason $reason,
        // The cart or order item this reason belongs to, when the line it
        // came from carried one — see {@see PlaceableLine::$lineId}.
        public ?string $lineId = null,
    ) {}
}
