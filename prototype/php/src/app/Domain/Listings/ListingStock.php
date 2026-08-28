<?php

declare(strict_types=1);

namespace App\Domain\Listings;

use App\Domain\DomainRuleViolation;
use InvalidArgumentException;

final readonly class ListingStock
{
    private function __construct(public ?int $quantity, public ListingStatus $status) {}

    /**
     * The title is the noun the refusal names, because the shopper reads the
     * message on the page they were sent back to. A null (made-to-order)
     * quantity stays null through the sale — the same "no cap" reading
     * {@see \App\Domain\Configurator\VariantStock} already gives a variant's
     * own untracked quantity.
     */
    public static function afterSale(?int $quantity, ListingStatus $status, int $sold, string $title): self
    {
        self::rejectAnEmptyChange($sold);

        if (! ListingAvailability::isPurchasable($status, $quantity)) {
            throw new DomainRuleViolation("“{$title}” is no longer for sale.");
        }

        if ($quantity !== null && $sold > $quantity) {
            throw new DomainRuleViolation("“{$title}” has only {$quantity} left.");
        }

        $remaining = $quantity === null ? null : $quantity - $sold;

        return new self($remaining, $remaining === 0 ? $status->transitionTo(ListingStatus::Sold) : $status);
    }

    public static function afterRestock(?int $quantity, ListingStatus $status, int $restored): self
    {
        self::rejectAnEmptyChange($restored);

        return new self(
            $quantity === null ? null : $quantity + $restored,
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
