<?php

declare(strict_types=1);

namespace App\Domain\Seller;

/**
 * The five figures {@see \App\Seller\SellerCustomers::tallyFor()} folds
 * over every buyer in one query: how many there are, how many are new
 * and how many are repeat buyers, and what they have ordered and spent.
 * {@see CustomerTally} adds the seller's conversation counts to these.
 */
final readonly class CustomerTallyFacts
{
    public function __construct(
        public int $customers,
        public int $newThisPeriod,
        public int $repeatBuyers,
        public int $orders,
        public int $spentCents,
    ) {}
}
