<?php

declare(strict_types=1);

namespace App\Domain\Seller;

use DateTimeImmutable;

$listingRow = function (string $id, int $views = 0, int $priceCents = 1000, string $title = 'Piece'): ListingTableRow {
    return new ListingTableRow(
        id: $id,
        title: $title,
        imageUrl: 'x',
        medium: null,
        dimensions: null,
        statusLabel: 'Live',
        statusTint: 'green',
        priceCents: $priceCents,
        quantity: 1,
        views: $views,
        favorites: 0,
        cartAdds: 0,
        sold: 0,
        revenueCents: 0,
        updatedAt: new DateTimeImmutable('2026-09-01 12:00:00'),
    );
};

$listingIdOf = fn (ListingTableRow $row): string => $row->id;

$customerRow = function (string $id, int $spentCents = 1000, int $orders = 1, string $name = 'Luna Lovegood'): CustomerRow {
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

$customerIdOf = fn (CustomerRow $row): string => $row->customerId;

it('sorts rows by the given column, descending', function () use ($listingRow, $listingIdOf): void {
    $rows = [$listingRow('a', views: 5), $listingRow('b', views: 20), $listingRow('c', views: 10)];

    $sorted = RowSort::apply(TableSort::of(ListingSortColumn::Views, SortDirection::Desc), $rows, $listingIdOf);

    expect(array_map(fn (ListingTableRow $row): string => $row->id, $sorted))->toBe(['b', 'c', 'a']);
});

it('sorts rows ascending', function () use ($listingRow, $listingIdOf): void {
    $rows = [$listingRow('a', priceCents: 300), $listingRow('b', priceCents: 100), $listingRow('c', priceCents: 200)];

    $sorted = RowSort::apply(TableSort::of(ListingSortColumn::Price, SortDirection::Asc), $rows, $listingIdOf);

    expect(array_map(fn (ListingTableRow $row): string => $row->id, $sorted))->toBe(['b', 'c', 'a']);
});

it('sorts a text column alphabetically', function () use ($listingRow, $listingIdOf): void {
    $rows = [$listingRow('a', title: 'Winter Elm'), $listingRow('b', title: 'Autumn Oak')];

    $sorted = RowSort::apply(TableSort::of(ListingSortColumn::Title, SortDirection::Asc), $rows, $listingIdOf);

    expect(array_map(fn (ListingTableRow $row): string => $row->id, $sorted))->toBe(['b', 'a']);
});

it('leaves an empty list empty', function () use ($listingIdOf): void {
    expect(RowSort::apply(TableSort::of(ListingSortColumn::Views, SortDirection::Desc), [], $listingIdOf))->toBe([]);
});

it('sorts customer rows by the given column, descending', function () use ($customerRow, $customerIdOf): void {
    $rows = [$customerRow('a', spentCents: 500), $customerRow('b', spentCents: 2000), $customerRow('c', spentCents: 1000)];

    $sorted = RowSort::apply(TableSort::of(CustomerSortColumn::Spent, SortDirection::Desc), $rows, $customerIdOf);

    expect(array_map(fn (CustomerRow $row): string => $row->customerId, $sorted))->toBe(['b', 'c', 'a']);
});

it('sorts a customer name alphabetically, ignoring case', function () use ($customerRow, $customerIdOf): void {
    $rows = [$customerRow('a', name: 'luna Lovegood'), $customerRow('b', name: 'Cho Chang')];

    $sorted = RowSort::apply(TableSort::of(CustomerSortColumn::Name, SortDirection::Asc), $rows, $customerIdOf);

    expect(array_map(fn (CustomerRow $row): string => $row->customerId, $sorted))->toBe(['b', 'a']);
});

it('breaks a tie on the given id, ascending, whichever way the column runs, on either table', function (
    string $table,
    SortDirection $direction,
) use ($listingRow, $listingIdOf, $customerRow, $customerIdOf): void {
    if ($table === 'listings') {
        $listingRows = [$listingRow('c', views: 5), $listingRow('a', views: 5), $listingRow('b', views: 5)];
        $sorted = RowSort::apply(TableSort::of(ListingSortColumn::Views, $direction), $listingRows, $listingIdOf);
        $ids = array_map($listingIdOf, $sorted);
    } else {
        $customerRows = [$customerRow('c', spentCents: 1000), $customerRow('a', spentCents: 1000), $customerRow('b', spentCents: 1000)];
        $sorted = RowSort::apply(TableSort::of(CustomerSortColumn::Spent, $direction), $customerRows, $customerIdOf);
        $ids = array_map($customerIdOf, $sorted);
    }

    expect($ids)->toBe(['a', 'b', 'c']);
})->with([
    'listings ascending' => ['listings', SortDirection::Asc],
    'listings descending' => ['listings', SortDirection::Desc],
    'customers ascending' => ['customers', SortDirection::Asc],
    'customers descending' => ['customers', SortDirection::Desc],
]);

it('keeps the id tie-break inside a descending column', function () use ($customerRow, $customerIdOf): void {
    $rows = [$customerRow('b', spentCents: 1000), $customerRow('a', spentCents: 1000), $customerRow('c', spentCents: 2000)];

    $sorted = RowSort::apply(TableSort::of(CustomerSortColumn::Spent, SortDirection::Desc), $rows, $customerIdOf);

    expect(array_map(fn (CustomerRow $row): string => $row->customerId, $sorted))->toBe(['c', 'a', 'b']);
});
