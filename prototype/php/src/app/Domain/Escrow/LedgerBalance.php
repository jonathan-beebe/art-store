<?php

declare(strict_types=1);

namespace App\Domain\Escrow;

use App\Domain\Money\Money;

final readonly class LedgerBalance
{
    private function __construct(public Money $held, public Money $available, public Money $paidOut) {}

    public function isPayable(): bool
    {
        return $this->available->isPositive();
    }

    /**
     * @param  list<LedgerMovement>  $movements
     */
    public static function from(array $movements): self
    {
        $totals = [
            LedgerEntryType::Held->value => Money::zero(),
            LedgerEntryType::Released->value => Money::zero(),
            LedgerEntryType::PaidOut->value => Money::zero(),
        ];

        foreach ($movements as $movement) {
            $totals[$movement->type->value] = $totals[$movement->type->value]->add($movement->amount);
        }

        $held = $totals[LedgerEntryType::Held->value];
        $released = $totals[LedgerEntryType::Released->value];
        // A payout movement carries a negative amount, so what has left escrow
        // is what the available balance adds and the paid-out total negates.
        $paidOut = $totals[LedgerEntryType::PaidOut->value];

        return new self(
            $held->subtract($released),
            $released->add($paidOut),
            Money::zero()->subtract($paidOut),
        );
    }
}
