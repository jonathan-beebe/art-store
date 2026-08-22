<?php

namespace App\Domain\Cart;

use App\Domain\Money\Money;
use DomainException;

final readonly class CartTotals
{
    /**
     * @param  array<int, Money>  $subtotalsBySeller
     */
    private function __construct(
        public int $itemCount,
        public Money $subtotal,
        private array $subtotalsBySeller,
    ) {}

    /**
     * @param  list<CartLine>  $lines
     */
    public static function forCheckout(array $lines): self
    {
        if ($lines === []) {
            throw new DomainException('An order needs at least one item.');
        }

        return self::from($lines);
    }

    /**
     * @param  list<CartLine>  $lines
     */
    public static function from(array $lines): self
    {
        $itemCount = 0;
        $subtotal = Money::fromCents(0);
        $subtotalsBySeller = [];

        foreach ($lines as $line) {
            $itemCount += $line->quantity;
            $subtotal = $subtotal->add($line->total());
            $sellerSubtotal = $subtotalsBySeller[$line->sellerId] ?? Money::fromCents(0);
            $subtotalsBySeller[$line->sellerId] = $sellerSubtotal->add($line->total());
        }

        ksort($subtotalsBySeller);

        return new self($itemCount, $subtotal, $subtotalsBySeller);
    }

    /**
     * @return array<int, Money>
     */
    public function subtotalsBySeller(): array
    {
        return $this->subtotalsBySeller;
    }
}
