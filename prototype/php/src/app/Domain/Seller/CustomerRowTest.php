<?php

declare(strict_types=1);

namespace App\Domain\Seller;

use DateTimeImmutable;

$row = function (
    string $name = 'Luna Lovegood',
    int $orders = 1,
    int $spentCents = 68000,
    ?DateTimeImmutable $firstOrderAt = null,
): CustomerRow {
    return new CustomerRow(
        customerId: 'cus_01',
        name: $name,
        email: 'luna@example.test',
        orders: $orders,
        spentCents: $spentCents,
        favorites: 3,
        conversations: 2,
        firstOrderAt: $firstOrderAt ?? new DateTimeImmutable('2026-06-01 09:00:00'),
        lastOrderAt: new DateTimeImmutable('2026-09-01 09:00:00'),
    );
};

it('reads what the buyer has spent as money', function () use ($row): void {
    expect($row(spentCents: 68000)->spent())->toBeMoney(68000);
});

it('calls a buyer with two or more orders a repeat buyer', function (int $orders, bool $expected) use ($row): void {
    expect($row(orders: $orders)->isRepeatBuyer())->toBe($expected);
})->with([
    'one order' => [1, false],
    'two orders' => [2, true],
    'nine orders' => [9, true],
]);

it('is new when the first order falls on or inside the window', function (string $firstOrderAt, bool $expected) use ($row): void {
    expect($row(firstOrderAt: new DateTimeImmutable($firstOrderAt))->isNewSince(new DateTimeImmutable('2026-08-01 00:00:00')))->toBe($expected);
})->with([
    'before the window' => ['2026-07-31 23:59:59', false],
    'on the window' => ['2026-08-01 00:00:00', true],
    'inside the window' => ['2026-08-14 09:00:00', true],
]);

it('reduces a name to two initials', function (string $name, string $expected) use ($row): void {
    expect($row(name: $name)->initials())->toBe($expected);
})->with([
    'two words' => ['Luna Lovegood', 'LL'],
    'three words' => ['Nymphadora Andromeda Tonks', 'NA'],
    'one word' => ['Hagrid', 'H'],
    'padded' => ['  Ginny   Weasley  ', 'GW'],
    'no name at all' => ['', ''],
]);
