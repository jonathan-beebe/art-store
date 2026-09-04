<?php

declare(strict_types=1);

namespace App\Domain\Seller;

use App\Domain\Escrow\PayoutPeriod;
use App\Domain\Money\Money;
use DateTimeImmutable;

it('sums the sales and fees of the live facts placed inside each period, and drops what falls outside every period', function (): void {
    $periods = [
        PayoutPeriod::endingBefore(new DateTimeImmutable('2026-08-17 00:00:00')),
        PayoutPeriod::endingBefore(new DateTimeImmutable('2026-08-24 00:00:00')),
    ];
    $sales = [
        new SaleFact(new DateTimeImmutable('2026-08-11 10:00:00'), Money::fromDollars('100.00'), Money::fromDollars('10.00'), isLive: true),
        new SaleFact(new DateTimeImmutable('2026-08-19 10:00:00'), Money::fromDollars('40.00'), Money::fromDollars('4.00'), isLive: true),
        new SaleFact(new DateTimeImmutable('2026-08-19 11:00:00'), Money::fromDollars('60.00'), Money::fromDollars('6.00'), isLive: true),
        new SaleFact(new DateTimeImmutable('2026-07-01 10:00:00'), Money::fromDollars('999.00'), Money::fromDollars('99.90'), isLive: true),
    ];

    [$first, $second] = PeriodFigures::bucket($periods, $sales, refunds: []);

    expect($first->orderCount)->toBe(1)
        ->and($first->sales->format())->toBe('$100.00')
        ->and($first->fees->format())->toBe('$10.00')
        ->and($second->orderCount)->toBe(2)
        ->and($second->sales->format())->toBe('$100.00')
        ->and($second->fees->format())->toBe('$10.00');
});

it('counts a declined or refunded order as placed, but leaves it out of sales and fees', function (): void {
    $period = PayoutPeriod::endingBefore(new DateTimeImmutable('2026-08-24 00:00:00'));
    $sales = [
        new SaleFact(new DateTimeImmutable('2026-08-19 10:00:00'), Money::fromDollars('100.00'), Money::fromDollars('10.00'), isLive: true),
        new SaleFact(new DateTimeImmutable('2026-08-19 11:00:00'), Money::fromDollars('50.00'), Money::fromDollars('5.00'), isLive: false),
    ];

    [$figures] = PeriodFigures::bucket([$period], $sales, refunds: []);

    expect($figures->orderCount)->toBe(2)
        ->and($figures->sales->format())->toBe('$100.00')
        ->and($figures->fees->format())->toBe('$10.00');
});

it('dates a refund by when it happened, not by when the sale it undoes was placed', function (): void {
    $period = PayoutPeriod::endingBefore(new DateTimeImmutable('2026-08-24 00:00:00'));
    $sales = [new SaleFact(new DateTimeImmutable('2026-08-01 10:00:00'), Money::fromDollars('100.00'), Money::fromDollars('10.00'), isLive: true)];
    $refunds = [new RefundFact(new DateTimeImmutable('2026-08-19 10:00:00'), Money::fromDollars('100.00'))];

    [$figures] = PeriodFigures::bucket([$period], $sales, $refunds);

    expect($figures->orderCount)->toBe(0)
        ->and($figures->sales->isZero())->toBeTrue()
        ->and($figures->refunds->format())->toBe('$100.00');
});

it('computes net as sales minus fees minus refunds', function (): void {
    $period = PayoutPeriod::endingBefore(new DateTimeImmutable('2026-08-24 00:00:00'));
    $sales = [new SaleFact(new DateTimeImmutable('2026-08-19 10:00:00'), Money::fromDollars('100.00'), Money::fromDollars('10.00'), isLive: true)];
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
