<?php

declare(strict_types=1);

namespace App\Domain\Cart;

use App\Domain\Money\Money;
use InvalidArgumentException;

final readonly class CartLine
{
    private function __construct(public string $sellerId, public Money $unitPrice, public int $quantity, private ?Money $precomputedTotal = null) {}

    public static function of(string $sellerId, Money $unitPrice, int $quantity): self
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException("A cart line covers at least one item, got {$quantity}.");
        }

        return new self($sellerId, $unitPrice, $quantity);
    }

    /**
     * A configured line's total is an itemized breakdown's own total —
     * surcharges, answer add-ons, and the quantity discount included — rather
     * than a flat `unitPrice * quantity`, so `$unitPrice` here is only a
     * representative per-unit figure for display.
     */
    public static function ofBreakdownTotal(string $sellerId, Money $unitPrice, int $quantity, Money $total): self
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException("A cart line covers at least one item, got {$quantity}.");
        }

        return new self($sellerId, $unitPrice, $quantity, $total);
    }

    public function total(): Money
    {
        return $this->precomputedTotal ?? $this->unitPrice->multiply($this->quantity);
    }
}
