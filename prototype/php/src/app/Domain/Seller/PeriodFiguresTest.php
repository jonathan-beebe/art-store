<?php

declare(strict_types=1);

namespace App\Domain\Seller;

use App\Domain\Escrow\PayoutPeriod;
use App\Domain\Money\Money;
use DateTimeImmutable;

it('sums the gross sales and fees of the facts placed inside each period, and drops what falls outside every period', function (): void {
    $periods = [
        PayoutPeriod::endingBefore(new DateTimeImmutable('2026-08-17 00:00:00')),
        PayoutPeriod::endingBefore(new DateTimeImmutable('2026-08-24 00:00:00')),
    ];
    $sales = [
        new SaleFact(new DateTimeImmutable('2026-08-11 10:00:00'), Money::fromDollars('100.00'), Money::fromDollars('10.00')),
        new SaleFact(new DateTimeImmutable('2026-08-19 10:00:00'), Money::fromDollars('40.00'), Money::fromDollars('4.00')),
        new SaleFact(new DateTimeImmutable('2026-08-19 11:00:00'), Money::fromDollars('60.00'), Money::fromDollars('6.00')),
        new SaleFact(new DateTimeImmutable('2026-07-01 10:00:00'), Money::fromDollars('999.00'), Money::fromDollars('99.90')),
    ];

    [$first, $second] = PeriodFigures::bucket($periods, $sales, refunds: []);

    expect($first->orderCount)->toBe(1)
        ->and($first->sales->format())->toBe('$100.00')
        ->and($first->fees->format())->toBe('$10.00')
        ->and($second->orderCount)->toBe(2)
        ->and($second->sales->format())->toBe('$100.00')
        ->and($second->fees->format())->toBe('$10.00');
});

it('keeps a later-refunded sale in the sales and fees of the period it was placed in', function (): void {
    $period = PayoutPeriod::endingBefore(new DateTimeImmutable('2026-08-24 00:00:00'));
    $sales = [new SaleFact(new DateTimeImmutable('2026-08-19 10:00:00'), Money::fromDollars('100.00'), Money::fromDollars('10.00'))];
    $refunds = [new RefundFact(new DateTimeImmutable('2026-08-25 10:00:00'), Money::fromDollars('90.00'))]; // a week later, outside this period

    [$figures] = PeriodFigures::bucket([$period], $sales, $refunds);

    expect($figures->orderCount)->toBe(1)
        ->and($figures->sales->format())->toBe('$100.00')
        ->and($figures->fees->format())->toBe('$10.00')
        ->and($figures->refunds->isZero())->toBeTrue()
        ->and($figures->net()->format())->toBe('$90.00');
});

it('nets a sale refunded inside its own period back to what the ledger actually kept', function (): void {
    $period = PayoutPeriod::endingBefore(new DateTimeImmutable('2026-08-24 00:00:00'));
    $sales = [new SaleFact(new DateTimeImmutable('2026-08-19 10:00:00'), Money::fromDollars('100.00'), Money::fromDollars('10.00'))];
    // IssueRefund sends the net (subtotal minus fee) back — the fee is forgone, never the full subtotal.
    $refunds = [new RefundFact(new DateTimeImmutable('2026-08-20 10:00:00'), Money::fromDollars('90.00'))];

    [$figures] = PeriodFigures::bucket([$period], $sales, $refunds);

    expect($figures->net()->isZero())->toBeTrue();
});

it('dates a refund by when it happened, not by when the sale it undoes was placed', function (): void {
    $period = PayoutPeriod::endingBefore(new DateTimeImmutable('2026-08-24 00:00:00'));
    $sales = [new SaleFact(new DateTimeImmutable('2026-08-01 10:00:00'), Money::fromDollars('100.00'), Money::fromDollars('10.00'))];
    $refunds = [new RefundFact(new DateTimeImmutable('2026-08-19 10:00:00'), Money::fromDollars('90.00'))];

    [$figures] = PeriodFigures::bucket([$period], $sales, $refunds);

    expect($figures->orderCount)->toBe(0)
        ->and($figures->sales->isZero())->toBeTrue()
        ->and($figures->refunds->format())->toBe('$90.00');
});

it('computes net as sales minus fees minus refunds', function (): void {
    $period = PayoutPeriod::endingBefore(new DateTimeImmutable('2026-08-24 00:00:00'));
    $sales = [new SaleFact(new DateTimeImmutable('2026-08-19 10:00:00'), Money::fromDollars('100.00'), Money::fromDollars('10.00'))];
    $refunds = [new RefundFact(new DateTimeImmutable('2026-08-20 10:00:00'), Money::fromDollars('20.00'))];

    [$figures] = PeriodFigures::bucket([$period], $sales, $refunds);

    expect($figures->net()->format())->toBe('$70.00');
});

it('folds an empty period to all zeros', function (): void {
    $period = PayoutPeriod::endingBefore(new DateTimeImmutable('2026-08-24 00:00:00'));

    [$figures] = PeriodFigures::bucket([$period], sales: [], refunds: []);

    expect($figures->orderCount)->toBe(0)
        ->and($figures->sales->isZero())->toBeTrue()
        ->and($figures->fees->isZero())->toBeTrue()
        ->and($figures->refunds->isZero())->toBeTrue()
        ->and($figures->net()->isZero())->toBeTrue();
});

it('reads its sales change against the period passed as previous', function (): void {
    $period = PayoutPeriod::endingBefore(new DateTimeImmutable('2026-08-24 00:00:00'));
    $previousPeriod = PayoutPeriod::endingBefore(new DateTimeImmutable('2026-08-17 00:00:00'));
    [$current] = PeriodFigures::bucket(
        [$period],
        [new SaleFact(new DateTimeImmutable('2026-08-19 10:00:00'), Money::fromDollars('150.00'), Money::fromDollars('15.00'))],
        [],
    );
    [$previous] = PeriodFigures::bucket(
        [$previousPeriod],
        [new SaleFact(new DateTimeImmutable('2026-08-12 10:00:00'), Money::fromDollars('100.00'), Money::fromDollars('10.00'))],
        [],
    );

    expect($current->salesChange($previous)->text)->toBe('+50.0%');
});
