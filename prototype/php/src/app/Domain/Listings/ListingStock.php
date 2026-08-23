<?php

declare(strict_types=1);

namespace App\Domain\Listings;

use DomainException;
use InvalidArgumentException;

final readonly class ListingStock
{
    private function __construct(public int $quantity, public ListingStatus $status) {}

    public static function afterSale(int $quantity, ListingStatus $status, int $sold): self
    {
        self::rejectAnEmptyChange($sold);

        if ($status !== ListingStatus::ForSale) {
            throw new DomainException("A listing that is {$status->value} cannot be sold.");
        }

        if ($sold > $quantity) {
            throw new DomainException("A listing with {$quantity} left cannot sell {$sold}.");
        }

        $remaining = $quantity - $sold;

        return new self($remaining, $remaining === 0 ? $status->transitionTo(ListingStatus::Sold) : $status);
    }

    public static function afterRestock(int $quantity, ListingStatus $status, int $restored): self
    {
        self::rejectAnEmptyChange($restored);

        return new self(
            $quantity + $restored,
            $status === ListingStatus::Sold ? $status->transitionTo(ListingStatus::ForSale) : $status,
        );
    }

    private static function rejectAnEmptyChange(int $items): void
    {
        if ($items < 1) {
            throw new InvalidArgumentException("A stock change covers at least one item, got {$items}.");
        }
    }
}
