<?php

declare(strict_types=1);

namespace App\Domain\Listings;

use App\Domain\DomainRuleViolation;
use InvalidArgumentException;

final readonly class ListingStock
{
    private function __construct(public int $quantity, public ListingStatus $status) {}

    /**
     * The title is the noun the refusal names, because the shopper reads the
     * message on the page they were sent back to.
     */
    public static function afterSale(int $quantity, ListingStatus $status, int $sold, string $title): self
    {
        self::rejectAnEmptyChange($sold);

        if (! ListingAvailability::isPurchasable($status, $quantity)) {
            throw new DomainRuleViolation("“{$title}” is no longer for sale.");
        }

        if ($sold > $quantity) {
            throw new DomainRuleViolation("“{$title}” has only {$quantity} left.");
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
