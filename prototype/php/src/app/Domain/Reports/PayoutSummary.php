<?php

declare(strict_types=1);

namespace App\Domain\Reports;

use App\Domain\Money\Money;

final readonly class PayoutSummary
{
    private function __construct(public int $count, public Money $total) {}

    /**
     * @param  list<int>  $amountsInCents  one entry per payout a run wrote
     */
    public static function of(array $amountsInCents): self
    {
        return new self(count($amountsInCents), Money::fromCents(array_sum($amountsInCents)));
    }
}
