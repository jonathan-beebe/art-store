<?php

namespace App\Domain\Cart;

use App\Domain\Money\Money;
use InvalidArgumentException;

final readonly class CartLine
{
    public function __construct(public int $sellerId, public Money $unitPrice, public int $quantity)
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException("A cart line covers at least one item, got {$quantity}.");
        }
    }

    public function total(): Money
    {
        return $this->unitPrice->multiply($this->quantity);
    }
}
