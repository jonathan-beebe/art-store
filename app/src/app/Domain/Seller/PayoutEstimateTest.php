<?php

declare(strict_types=1);

namespace App\Domain\Seller;

use App\Domain\Escrow\LedgerBalance;
use App\Domain\Escrow\LedgerEntryType;
use App\Domain\Escrow\LedgerMovement;
use App\Domain\Escrow\PayoutPeriod;
use App\Domain\Money\Money;
use DateTimeImmutable;

it('takes its amount from the ledger balance available to pay out', function (): void {
    $balance = LedgerBalance::from([
        LedgerMovement::hold(Money::fromDollars('90.00')),
        LedgerMovement::of(LedgerEntryType::Released, Money::fromDollars('90.00')),
    ]);
    $period = PayoutPeriod::containing(new DateTimeImmutable('2026-08-22 09:30:00'));

    $estimate = PayoutEstimate::from($balance, $period, releasedOrderCount: 3);

    expect($estimate->amount->equals($balance->available))->toBeTrue()
        ->and($estimate->amount->format())->toBe('$90.00')
        ->and($estimate->releasedOrderCount)->toBe(3);
});

it('pays out the Monday after the period it names, whether that period is in progress or already complete', function (string $moment, string $expectedPayoutDate): void {
    $period = PayoutPeriod::containing(new DateTimeImmutable($moment));

    $estimate = PayoutEstimate::from(LedgerBalance::from([]), $period, releasedOrderCount: 0);

    expect($estimate->payoutDate->format('Y-m-d'))->toBe($expectedPayoutDate);
})->with([
    'mid-week, the period still in progress' => ['2026-08-19 09:00:00', '2026-08-24'],
    'the last moment of the period' => ['2026-08-23 23:59:59', '2026-08-24'],
]);

it('reads a balance still owed to the platform as carrying negative', function (): void {
    $balance = LedgerBalance::from([
        LedgerMovement::hold(Money::fromDollars('90.00')),
        LedgerMovement::of(LedgerEntryType::Released, Money::fromDollars('90.00')),
        LedgerMovement::of(LedgerEntryType::PaidOut, Money::fromDollars('-90.00')),
        LedgerMovement::refund(Money::fromDollars('90.00')),
    ]);
    $period = PayoutPeriod::containing(new DateTimeImmutable('2026-08-22 09:30:00'));

    $estimate = PayoutEstimate::from($balance, $period, releasedOrderCount: 0);

    expect($estimate->amount->format())->toBe('-$90.00')
        ->and($estimate->isCarryingNegative())->toBeTrue();
});

it('does not read a zero or positive balance as carrying negative', function (): void {
    $period = PayoutPeriod::containing(new DateTimeImmutable('2026-08-22 09:30:00'));

    $zero = PayoutEstimate::from(LedgerBalance::from([]), $period, releasedOrderCount: 0);
    $positive = PayoutEstimate::from(
        LedgerBalance::from([
            LedgerMovement::hold(Money::fromDollars('50.00')),
            LedgerMovement::of(LedgerEntryType::Released, Money::fromDollars('50.00')),
        ]),
        $period,
        releasedOrderCount: 1,
    );

    expect($zero->isCarryingNegative())->toBeFalse()
        ->and($positive->isCarryingNegative())->toBeFalse();
});
