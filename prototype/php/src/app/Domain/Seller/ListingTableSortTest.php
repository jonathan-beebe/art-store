<?php

declare(strict_types=1);

namespace App\Domain\Seller;

use DateTimeImmutable;

$row = function (string $id = 'lst_01', string $title = 'Piece', int $priceCents = 1000, int $views = 0): ListingTableRow {
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

it('sorts rows by the given column, descending', function () use ($row): void {
    $rows = [$row(id: 'a', views: 5), $row(id: 'b', views: 20), $row(id: 'c', views: 10)];

    $sorted = ListingTableSort::apply(ListingSort::of(ListingSortColumn::Views, ListingSortDirection::Desc), $rows);

    expect(array_map(fn (ListingTableRow $r): string => $r->id, $sorted))->toBe(['b', 'c', 'a']);
});

it('sorts rows ascending', function () use ($row): void {
    $rows = [$row(id: 'a', priceCents: 300), $row(id: 'b', priceCents: 100), $row(id: 'c', priceCents: 200)];

    $sorted = ListingTableSort::apply(ListingSort::of(ListingSortColumn::Price, ListingSortDirection::Asc), $rows);

    expect(array_map(fn (ListingTableRow $r): string => $r->id, $sorted))->toBe(['b', 'c', 'a']);
});

it('sorts a text column alphabetically', function () use ($row): void {
    $rows = [$row(id: 'a', title: 'Winter Elm'), $row(id: 'b', title: 'Autumn Oak')];

    $sorted = ListingTableSort::apply(ListingSort::of(ListingSortColumn::Title, ListingSortDirection::Asc), $rows);

    expect(array_map(fn (ListingTableRow $r): string => $r->id, $sorted))->toBe(['b', 'a']);
});

it('leaves an empty list empty', function (): void {
    expect(ListingTableSort::apply(ListingSort::default(), []))->toBe([]);
});
