<?php

declare(strict_types=1);

namespace App\Domain\Seller;

use App\Domain\Money\Money;
use App\Domain\Orders\FulfillmentStatus;
use DateTimeImmutable;

/**
 * One order placed inside a payout period, as a period's sales table or
 * statement lists it.
 */
final readonly class PeriodSaleRow
{
    public function __construct(
        public string $fulfillmentId,
        public DateTimeImmutable $placedAt,
        public string $buyerName,
        public string $itemLabel,
        public Money $subtotal,
        public Money $fee,
        public Money $net,
        public FulfillmentStatus $status,
    ) {}
}
