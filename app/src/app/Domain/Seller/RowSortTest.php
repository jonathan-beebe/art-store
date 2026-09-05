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

it('sorts rows by the given column, descending', function () use ($listingRow, $listingIdOf): void {
    $rows = [$listingRow('a', views: 5), $listingRow('b', views: 20), $listingRow('c', views: 10)];

    $sorted = RowSort::apply(ListingSortColumn::defaultSort(), $rows, $listingIdOf);

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
    expect(RowSort::apply(ListingSortColumn::defaultSort(), [], $listingIdOf))->toBe([]);
});

it('breaks a tie on the given id, ascending, whichever way the column runs', function (SortDirection $direction) use ($listingRow, $listingIdOf): void {
    $rows = [$listingRow('c', views: 5), $listingRow('a', views: 5), $listingRow('b', views: 5)];

    $sorted = RowSort::apply(TableSort::of(ListingSortColumn::Views, $direction), $rows, $listingIdOf);

    expect(array_map($listingIdOf, $sorted))->toBe(['a', 'b', 'c']);
})->with([
    'ascending' => [SortDirection::Asc],
    'descending' => [SortDirection::Desc],
]);
