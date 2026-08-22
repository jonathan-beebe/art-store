<?php

namespace App\Domain\Reports;

use PHPUnit\Framework\TestCase;

final class PayoutSummaryTest extends TestCase
{
    public function test_it_counts_the_payouts_a_run_wrote(): void
    {
        $summary = PayoutSummary::of([9000, 4500]);

        $this->assertSame(2, $summary->count);
    }

    public function test_it_totals_the_amounts_paid_out(): void
    {
        $summary = PayoutSummary::of([9000, 4500]);

        $this->assertSame('$135.00', $summary->total->format());
    }

    public function test_a_run_that_paid_nobody_totals_zero(): void
    {
        $summary = PayoutSummary::of([]);

        $this->assertSame(0, $summary->count);
        $this->assertSame('$0.00', $summary->total->format());
    }
}
