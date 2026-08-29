<?php

declare(strict_types=1);

namespace App\Domain\Orders;

use App\Domain\Listings\ListingStatus;

/**
 * Whether a set of lines may become an order, and what stands in the way of
 * every one that may not. Pure: it judges the lines it is given against the
 * listing state already folded into them, reading nothing itself — the
 * caller reads the cart or the order, and the listings behind it, inside its
 * own transaction, and folds what it read into `PlaceableLine`s before
 * asking here.
 */
final readonly class OrderPlacementPlan
{
    /**
     * @param  list<PlaceableLine>  $lines
     * @param  list<BlockedLine>  $blocked
     */
    private function __construct(
        public array $lines,
        public array $blocked,
    ) {}

    /**
     * @param  list<PlaceableLine>  $lines
     */
    public static function for(array $lines): self
    {
        $blocked = [];

        foreach ($lines as $line) {
            $reason = self::unavailableReason($line);

            if ($reason !== null) {
                $blocked[] = new BlockedLine($line->listingId, $line->title, $reason, $line->lineId);
            }
        }

        return new self($lines, $blocked);
    }

    public function isPlaceable(): bool
    {
        return $this->blocked === [];
    }

    /**
     * The reason the cart or pay page marks a line with, or null for a line
     * nothing stands in the way of. Matched by line id when the blocked line
     * carries one — a cart can hold two lines of the same listing (two
     * configurations) — falling back to the listing id for a caller that
     * never set one.
     */
    public function blockedReasonFor(string $key): ?UnavailableReason
    {
        foreach ($this->blocked as $line) {
            if ($line->lineId === $key || ($line->lineId === null && $line->listingId === $key)) {
                return $line->reason;
            }
        }

        return null;
    }

    /**
     * A removal outranks whatever the listing status says; nothing left to
     * sell reads as sold out rather than short of stock. A configured line
     * reads its availability off the variant (and, if serialized, the
     * specific unit) it resolved to instead of the listing's own quantity —
     * `docs/item-configurator.md` §3.
     */
    private static function unavailableReason(PlaceableLine $line): ?UnavailableReason
    {
        return match (true) {
            $line->hasActiveRemoval => UnavailableReason::Removed,
            $line->status === ListingStatus::Sold => UnavailableReason::SoldOut,
            $line->status !== ListingStatus::ForSale => UnavailableReason::OffSale,
            $line->configured => self::unavailableReasonForConfiguredLine($line),
            $line->availableQuantity === null => null,
            $line->availableQuantity < 1 => UnavailableReason::SoldOut,
            $line->quantity > $line->availableQuantity => UnavailableReason::ShortStock,
            default => null,
        };
    }

    private static function unavailableReasonForConfiguredLine(PlaceableLine $line): ?UnavailableReason
    {
        if (! $line->variantEnabled) {
            return UnavailableReason::OffSale;
        }

        if ($line->serialized) {
            return $line->unitAvailable ? null : UnavailableReason::SoldOut;
        }

        if ($line->variantRemainingQuantity === null) {
            return null;
        }

        if ($line->variantRemainingQuantity < 1) {
            return UnavailableReason::SoldOut;
        }

        return $line->quantity > $line->variantRemainingQuantity ? UnavailableReason::ShortStock : null;
    }
}
