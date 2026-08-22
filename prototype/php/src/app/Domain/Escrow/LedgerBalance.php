<?php

namespace App\Domain\Escrow;

use App\Domain\Money\Money;

final readonly class LedgerBalance
{
    private function __construct(public Money $held, public Money $available, public Money $paidOut) {}

    public function isPayable(): bool
    {
        return $this->available->cents > 0;
    }

    /**
     * @param  list<LedgerMovement>  $movements
     */
    public static function from(array $movements): self
    {
        $totals = [
            LedgerEntryType::Held->value => 0,
            LedgerEntryType::Released->value => 0,
            LedgerEntryType::PaidOut->value => 0,
        ];

        foreach ($movements as $movement) {
            $totals[$movement->type->value] += $movement->amount->cents;
        }

        $held = $totals[LedgerEntryType::Held->value];
        $released = $totals[LedgerEntryType::Released->value];
        $paidOut = $totals[LedgerEntryType::PaidOut->value];

        return new self(
            Money::fromCents($held - $released),
            Money::fromCents($released + $paidOut),
            Money::fromCents(-$paidOut),
        );
    }
}
