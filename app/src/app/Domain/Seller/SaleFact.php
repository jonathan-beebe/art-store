<?php

declare(strict_types=1);

namespace App\Domain\Seller;

use App\Domain\Money\Money;
use DateTimeImmutable;

/**
 * One order placed inside a period, as {@see PeriodFigures} folds it: when
 * it was placed, and the subtotal and platform fee it carries. A later
 * refund is a separate {@see RefundFact}, dated by when the refund
 * happened, and nets back through the period it lands in.
 */
final readonly class SaleFact
{
    public function __construct(
        public DateTimeImmutable $placedAt,
        public Money $subtotal,
        public Money $fee,
    ) {}
}
