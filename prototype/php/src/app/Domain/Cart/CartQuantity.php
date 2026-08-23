<?php

declare(strict_types=1);

namespace App\Domain\Cart;

use DomainException;
use InvalidArgumentException;

final class CartQuantity
{
    public static function withinStock(int $requested, int $available): int
    {
        if ($requested < 1) {
            throw new InvalidArgumentException("A cart holds at least one of a listing, got {$requested}.");
        }

        if ($available < 1) {
            throw new DomainException('That listing is sold out.');
        }

        return min($requested, $available);
    }
}
