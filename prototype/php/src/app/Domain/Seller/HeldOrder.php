<?php

declare(strict_types=1);

namespace App\Domain\Seller;

use App\Domain\Money\Money;
use DateTimeImmutable;

/**
 * One row of the held-in-escrow list: an order whose money has not
 * released, with what it will net once it does and how far it has gotten
 * toward the buyer.
 */
final readonly class HeldOrder
{
    public function __construct(
        public string $fulfillmentId,
        public string $buyerName,
        public string $itemLabel,
        public Money $net,
        public HeldState $state,
        public ?DateTimeImmutable $shippedAt,
    ) {}
}
