<?php

declare(strict_types=1);

namespace App\Domain\Seller;

use App\Domain\Money\Money;
use DateTimeImmutable;

/**
 * One fulfillment placed in a period, as {@see PeriodFigures} folds it: when
 * the order behind it was placed, the subtotal and platform fee it carries,
 * and whether it is still live. A declined or refunded fulfillment still
 * counts as an order placed that period; it earns the platform no fee, so
 * `isLive` keeps it out of the sales and fee totals. {@see RefundFact}
 * carries what it gave back, dated by when the refund happened.
 */
final readonly class SaleFact
{
    public function __construct(
        public DateTimeImmutable $placedAt,
        public Money $subtotal,
        public Money $fee,
        public bool $isLive,
    ) {}
}
