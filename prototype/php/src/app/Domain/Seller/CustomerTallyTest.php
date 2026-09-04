<?php

declare(strict_types=1);

namespace App\Domain\Seller;

use DateTimeImmutable;

$row = function (string $id, int $orders, int $spentCents, string $firstOrderAt): CustomerRow {
    return new CustomerRow(
        customerId: $id,
        name: 'Luna Lovegood',
        email: null,
        orders: $orders,
        spentCents: $spentCents,
        favorites: 0,
        conversations: 0,
        firstOrderAt: new DateTimeImmutable($firstOrderAt),
        lastOrderAt: new DateTimeImmutable('2026-09-01 09:00:00'),
    );
};

$rangeStart = new DateTimeImmutable('2026-08-01 00:00:00');

it('counts buyers, new buyers, repeat buyers, and their money', function () use ($row, $rangeStart): void {
    $rows = [
        $row('a', 1, 10000, '2026-01-01 09:00:00'),
        $row('b', 3, 50000, '2026-08-14 09:00:00'),
    ];

    $tally = CustomerTally::of($rows, $rangeStart, openConversations: 5, unreadConversations: 2);

    expect($tally->customers)->toBe(2)
        ->and($tally->newThisPeriod)->toBe(1)
        ->and($tally->repeatBuyers)->toBe(1)
        ->and($tally->orders)->toBe(4)
        ->and($tally->spentCents)->toBe(60000)
        ->and($tally->openConversations)->toBe(5)
        ->and($tally->unreadConversations)->toBe(2);
});

it('reads the repeat share as whole percent', function () use ($row, $rangeStart): void {
    $rows = [
        $row('a', 1, 10000, '2026-01-01 09:00:00'),
        $row('b', 2, 10000, '2026-01-01 09:00:00'),
        $row('c', 2, 10000, '2026-01-01 09:00:00'),
    ];

    expect(CustomerTally::of($rows, $rangeStart, 0, 0)->repeatShare())->toBe(67);
});

it('reads the average order as money', function () use ($row, $rangeStart): void {
    $rows = [$row('a', 1, 10000, '2026-01-01 09:00:00'), $row('b', 3, 50000, '2026-01-01 09:00:00')];

    expect(CustomerTally::of($rows, $rangeStart, 0, 0)->averageOrder())->toBeMoney(15000);
});

it('has no share and no average before there is a buyer', function () use ($rangeStart): void {
    $tally = CustomerTally::of([], $rangeStart, 0, 0);

    expect($tally->customers)->toBe(0)
        ->and($tally->repeatShare())->toBeNull()
        ->and($tally->averageOrder())->toBeNull();
});
