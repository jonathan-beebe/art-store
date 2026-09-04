<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Money\Money;
use DateTimeImmutable;

it('reads the card line as orders, spend, and since', function (): void {
    $facts = new CustomerFacts(
        name: 'Luna Lovegood',
        email: 'luna@example.test',
        orders: 3,
        spend: Money::fromCents(61200),
        since: new DateTimeImmutable('2026-08-12 09:00:00'),
    );

    expect($facts->line())->toBe('3 orders · $612.00 · since Aug 12, 2026');
});

it('counts one order in the singular', function (): void {
    $facts = new CustomerFacts(
        name: 'Luna Lovegood',
        email: null,
        orders: 1,
        spend: Money::fromCents(4500),
        since: new DateTimeImmutable('2026-08-12 09:00:00'),
    );

    expect($facts->line())->toStartWith('1 order · $45.00');
});

it('says nothing about since on a buyer with nothing behind them', function (): void {
    $facts = new CustomerFacts(
        name: 'Luna Lovegood',
        email: null,
        orders: 0,
        spend: Money::zero(),
        since: null,
    );

    expect($facts->line())->toBe('0 orders · $0.00');
});
