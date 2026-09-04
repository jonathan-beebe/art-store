<?php

declare(strict_types=1);

namespace App\Domain\Seller;

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

it('opens on spent, largest first', function (): void {
    expect(CustomerSortColumn::default())->toBe(CustomerSortColumn::Spent);

    $sort = CustomerSortColumn::defaultSort();

    expect($sort->isColumn(CustomerSortColumn::Spent))->toBeTrue()
        ->and($sort->direction)->toBe(SortDirection::Desc);
});
