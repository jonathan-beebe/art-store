<?php

declare(strict_types=1);

namespace App\Domain\Escrow;

use App\Domain\Money\Money;

/**
 * One line of the ledger as the fold reads it. The fulfillment it belongs to
 * is part of the movement because a refund has to be netted against the same
 * fulfillment's own hold or release, never against another sale's.
 */
final readonly class LedgerMovement
{
    private function __construct(
        public LedgerEntryType $type,
        public Money $amount,
        public ?string $fulfillmentId = null,
    ) {}

    public static function hold(Money $net): self
    {
        return new self(LedgerEntryType::Held, $net);
    }

    public static function release(Money $net): self
    {
        return new self(LedgerEntryType::Released, $net);
    }

    /**
     * A refund runs the fulfillment's net back out of the seller's money, so
     * the amount is the negative of what the hold put in.
     */
    public static function refund(Money $net): self
    {
        return new self(LedgerEntryType::Refunded, Money::fromCents(-$net->cents));
    }

    public static function payout(Money $net): self
    {
        return new self(LedgerEntryType::PaidOut, Money::fromCents(-$net->cents));
    }

    public static function of(LedgerEntryType $type, Money $amount, ?string $fulfillmentId = null): self
    {
        return new self($type, $amount, $fulfillmentId);
    }
}
