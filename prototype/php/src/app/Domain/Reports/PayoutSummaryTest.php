<?php

declare(strict_types=1);

namespace App\Domain\Reports;

use App\Domain\Money\Money;

it('counts the payouts a run wrote', function (): void {
    $summary = PayoutSummary::of([Money::fromCents(9000), Money::fromCents(4500)]);

    expect($summary->count)->toBe(2);
});

it('totals the amounts paid out', function (): void {
    $summary = PayoutSummary::of([Money::fromCents(9000), Money::fromCents(4500)]);

    expect($summary->total->format())->toBe('$135.00');
});

it('totals zero for a run that paid nobody', function (): void {
    $summary = PayoutSummary::of([]);

    expect($summary->count)->toBe(0)
        ->and($summary->total->format())->toBe('$0.00');
});
