<?php

declare(strict_types=1);

namespace App\Domain\Seller;

use DateTimeImmutable;

$row = new CustomerRow(
    customerId: 'cus_01',
    name: 'Luna Lovegood',
    email: 'luna@example.test',
    orders: 4,
    spentCents: 68000,
    favorites: 3,
    conversations: 2,
    firstOrderAt: new DateTimeImmutable('2026-06-01 09:00:00'),
    lastOrderAt: new DateTimeImmutable('2026-09-01 09:00:00'),
);

it('names every column', function (CustomerSortColumn $column, string $expected): void {
    expect($column->label())->toBe($expected);
})->with([
    [CustomerSortColumn::Name, 'Customer'],
    [CustomerSortColumn::Orders, 'Orders'],
    [CustomerSortColumn::Spent, 'Spent'],
    [CustomerSortColumn::Favorites, 'Favorites'],
    [CustomerSortColumn::LastOrder, 'Last order'],
    [CustomerSortColumn::Conversations, 'Conversations'],
    [CustomerSortColumn::Since, 'Since'],
]);

it('right-aligns the counted columns alone', function (): void {
    $right = array_values(array_filter(CustomerSortColumn::cases(), fn (CustomerSortColumn $column): bool => $column->alignsRight()));

    expect($right)->toBe([
        CustomerSortColumn::Orders,
        CustomerSortColumn::Spent,
        CustomerSortColumn::Favorites,
        CustomerSortColumn::Conversations,
    ]);
});

it('reads the value each column sorts by', function (CustomerSortColumn $column, int|string $expected) use ($row): void {
    expect($column->keyOf($row))->toBe($expected);
})->with([
    [CustomerSortColumn::Name, 'luna lovegood'],
    [CustomerSortColumn::Orders, 4],
    [CustomerSortColumn::Spent, 68000],
    [CustomerSortColumn::Favorites, 3],
    [CustomerSortColumn::LastOrder, (new DateTimeImmutable('2026-09-01 09:00:00'))->getTimestamp()],
    [CustomerSortColumn::Conversations, 2],
    [CustomerSortColumn::Since, (new DateTimeImmutable('2026-06-01 09:00:00'))->getTimestamp()],
]);
