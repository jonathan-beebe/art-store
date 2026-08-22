<?php

namespace App\Domain\Escrow;

use App\Domain\Money\Money;

final readonly class LedgerMovement
{
    private function __construct(public LedgerEntryType $type, public Money $amount) {}

    public static function hold(Money $net): self
    {
        return new self(LedgerEntryType::Held, $net);
    }

    public static function release(Money $net): self
    {
        return new self(LedgerEntryType::Released, $net);
    }

    public static function payout(Money $net): self
    {
        return new self(LedgerEntryType::PaidOut, Money::fromCents(-$net->cents));
    }

    public static function of(LedgerEntryType $type, Money $amount): self
    {
        return new self($type, $amount);
    }
}
