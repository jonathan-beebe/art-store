<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

use App\Domain\DomainRuleViolation;
use InvalidArgumentException;

/**
 * {@see \App\Domain\Listings\ListingStock} for a non-serialized variant: a
 * null quantity is untracked and stays null through every sale and restock —
 * the same "no cap" reading {@see VariantAvailability} already gives it — and
 * a tracked quantity moves exactly the way `listings.quantity` does, minus
 * the status transition, because a variant carries no status of its own.
 */
final class VariantStock
{
    private function __construct() {} // @codeCoverageIgnore

    public static function afterSale(?int $quantity, int $sold): ?int
    {
        self::rejectAnEmptyChange($sold);

        if ($quantity === null) {
            return null;
        }

        if ($sold > $quantity) {
            throw new DomainRuleViolation("That configuration has only {$quantity} left.");
        }

        return $quantity - $sold;
    }

    public static function afterRestock(?int $quantity, int $restored): ?int
    {
        self::rejectAnEmptyChange($restored);

        return $quantity === null ? null : $quantity + $restored;
    }

    private static function rejectAnEmptyChange(int $items): void
    {
        if ($items < 1) {
            throw new InvalidArgumentException("A stock change covers at least one item, got {$items}.");
        }
    }
}
