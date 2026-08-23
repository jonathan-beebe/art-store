<?php

declare(strict_types=1);

namespace App\Domain\Cart;

use App\Domain\Money\Money;
use InvalidArgumentException;

final readonly class CartLine
{
    private function __construct(public int $sellerId, public Money $unitPrice, public int $quantity) {}

    public static function of(int $sellerId, Money $unitPrice, int $quantity): self
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException("A cart line covers at least one item, got {$quantity}.");
        }

        return new self($sellerId, $unitPrice, $quantity);
    }

    public function total(): Money
    {
        return $this->unitPrice->multiply($this->quantity);
    }
}
