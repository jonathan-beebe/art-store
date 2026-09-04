<?php

declare(strict_types=1);

namespace App\Domain\Seller;

use DateTimeImmutable;

$row = function (string $id, string $name = 'Luna Lovegood', int $spentCents = 1000, int $orders = 1): CustomerRow {
    return new CustomerRow(
        customerId: $id,
        name: $name,
        email: null,
        orders: $orders,
        spentCents: $spentCents,
        favorites: 0,
        conversations: 0,
        firstOrderAt: new DateTimeImmutable('2026-06-01 09:00:00'),
        lastOrderAt: new DateTimeImmutable('2026-09-01 09:00:00'),
    );
};

it('sorts by the given column, descending', function () use ($row): void {
    $rows = [$row('a', spentCents: 500), $row('b', spentCents: 2000), $row('c', spentCents: 1000)];

    $sorted = CustomerTableSort::apply(CustomerSort::of(CustomerSortColumn::Spent, SortDirection::Desc), $rows);

    expect(array_map(fn (CustomerRow $sortedRow): string => $sortedRow->customerId, $sorted))->toBe(['b', 'c', 'a']);
});

it('sorts ascending', function () use ($row): void {
    $rows = [$row('a', orders: 3), $row('b', orders: 1), $row('c', orders: 2)];

    $sorted = CustomerTableSort::apply(CustomerSort::of(CustomerSortColumn::Orders, SortDirection::Asc), $rows);

    expect(array_map(fn (CustomerRow $sortedRow): string => $sortedRow->customerId, $sorted))->toBe(['b', 'c', 'a']);
});

it('sorts a name alphabetically, ignoring case', function () use ($row): void {
    $rows = [$row('a', name: 'luna Lovegood'), $row('b', name: 'Cho Chang')];

    $sorted = CustomerTableSort::apply(CustomerSort::of(CustomerSortColumn::Name, SortDirection::Asc), $rows);

    expect(array_map(fn (CustomerRow $sortedRow): string => $sortedRow->customerId, $sorted))->toBe(['b', 'a']);
});

it('breaks a tie on the customer id, ascending', function () use ($row): void {
    $rows = [$row('c'), $row('a'), $row('b')];

    $sorted = CustomerTableSort::apply(CustomerSort::of(CustomerSortColumn::Spent, SortDirection::Asc), $rows);

    expect(array_map(fn (CustomerRow $sortedRow): string => $sortedRow->customerId, $sorted))->toBe(['a', 'b', 'c']);
});

it('leaves an empty list empty', function (): void {
    expect(CustomerTableSort::apply(CustomerSort::default(), []))->toBe([]);
});
