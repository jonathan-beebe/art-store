<?php

declare(strict_types=1);

namespace App\Domain\Customers;

/**
 * One listing and the quantity of it held in a cart, stripped down to what
 * `CustomerMergePlan` needs to fold two carts together. `App\Domain\Cart\CartLine`
 * carries a seller and a price too, for totalling a cart that is already
 * settled on one owner — the merge runs before that, so it works from less.
 */
final readonly class CustomerCartLine
{
    public function __construct(
        public string $listingId,
        public int $quantity,
    ) {}
}
