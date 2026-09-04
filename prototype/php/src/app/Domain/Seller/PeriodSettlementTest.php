<?php

declare(strict_types=1);

namespace App\Domain\Seller;

use DateTimeImmutable;

it('reads the current period as in progress, whatever else is true of it', function (): void {
    $settlement = PeriodSettlement::of(isCurrent: true, hasPayoutRow: true, paidAt: new DateTimeImmutable('2026-08-24'));

    expect($settlement->status)->toBe(PeriodPayoutStatus::InProgress)
        ->and($settlement->paidAt)->toBeNull();
});

it('reads a completed period with no payout row as settled at zero', function (): void {
    $settlement = PeriodSettlement::of(isCurrent: false, hasPayoutRow: false, paidAt: null);

    expect($settlement->status)->toBe(PeriodPayoutStatus::None);
});

it('reads a payout row as paid, and carries its date', function (): void {
    $paidAt = new DateTimeImmutable('2026-08-24 06:00:00');

    $settlement = PeriodSettlement::of(isCurrent: false, hasPayoutRow: true, paidAt: $paidAt);

    expect($settlement->status)->toBe(PeriodPayoutStatus::Paid)
        ->and($settlement->paidAt)->toBe($paidAt);
});
