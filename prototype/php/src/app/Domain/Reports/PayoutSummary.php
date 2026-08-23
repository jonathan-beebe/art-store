<?php

declare(strict_types=1);

namespace App\Domain\Reports;

use App\Domain\Money\Money;

final readonly class PayoutSummary
{
    private function __construct(public int $count, public Money $total) {}

    /**
     * @param  list<Money>  $amounts  one entry per payout a run wrote
     */
    public static function of(array $amounts): self
    {
        return new self(
            count($amounts),
            array_reduce($amounts, fn (Money $total, Money $amount): Money => $total->add($amount), Money::zero()),
        );
    }
}
