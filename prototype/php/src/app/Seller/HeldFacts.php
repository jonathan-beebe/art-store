<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Money\Money;

/**
 * Escrow in two figures: what is held, and how many parcels are holding
 * it. {@see HeldEscrow::tallyFor()} answers this for a page that needs the
 * numbers and none of the rows.
 */
final readonly class HeldFacts
{
    public function __construct(
        public Money $total,
        public int $orders,
    ) {}
}
