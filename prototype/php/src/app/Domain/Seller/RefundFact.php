<?php

declare(strict_types=1);

namespace App\Domain\Seller;

use App\Domain\Money\Money;
use DateTimeImmutable;

/**
 * One `refunded` ledger movement as {@see PeriodFigures} folds it, dated by
 * when the refund happened rather than when the original sale was placed —
 * a period's refund total is money that left during that week, whichever
 * week the sale itself landed in.
 */
final readonly class RefundFact
{
    public function __construct(
        public DateTimeImmutable $occurredAt,
        public Money $amount,
    ) {}
}
