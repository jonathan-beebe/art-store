<?php

declare(strict_types=1);

namespace App\Domain\Orders;

use App\Domain\Listings\ListingStatus;

/**
 * A cart or order line as placement judges it: the quantity asked for,
 * against what the listing behind it allows right now — or, for a configured
 * line, against the variant (and, if serialized, the specific unit) it
 * resolved to instead. `$listingId`/`$status`/`$hasActiveRemoval` still gate
 * a configured line (a removed or unpublished listing blocks every line on
 * it); `$availableQuantity` is the legacy, listing-tracked figure and is
 * unused when `$configured` is true.
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
        // Unique per line — a cart or order item id — so two lines of the
        // same listing (two configurations) are told apart. Null keeps the
        // old, listing-keyed lookup for a caller that never set it.
        public ?string $lineId = null,
        public bool $configured = false,
        public bool $variantEnabled = true,
        public bool $serialized = false,
        // Meaningful only when `$serialized`: whether the specific unit this
        // line claimed is still `available`.
        public bool $unitAvailable = true,
        // Meaningful only when `$configured` and not `$serialized`: the
        // variant's own tracked quantity, or null when it carries none.
        public ?int $variantRemainingQuantity = null,
    ) {}
}
