<?php

declare(strict_types=1);

namespace App\Domain\Seller;

use DateTimeImmutable;

$row = function (
    string $id = 'lst_01',
    string $title = 'The Burrow at Dusk',
    string $statusLabel = 'Live',
    int $priceCents = 68000,
    ?int $quantity = 1,
    int $views = 412,
    int $favorites = 31,
    int $cartAdds = 14,
    int $sold = 1,
    int $revenueCents = 68000,
    ?DateTimeImmutable $updatedAt = null,
): ListingTableRow {
    return new ListingTableRow(
        id: $id,
        title: $title,
        imageUrl: 'https://example.test/burrow.jpg',
        medium: 'Painting',
        dimensions: '24 x 36 in',
        statusLabel: $statusLabel,
        statusTint: 'green',
        priceCents: $priceCents,
        quantity: $quantity,
        views: $views,
        favorites: $favorites,
        cartAdds: $cartAdds,
        sold: $sold,
        revenueCents: $revenueCents,
        updatedAt: $updatedAt ?? new DateTimeImmutable('2026-09-01 12:00:00'),
    );
};

it('labels every column', function (ListingSortColumn $column, string $expected): void {
    expect($column->label())->toBe($expected);
})->with([
    [ListingSortColumn::Title, 'Listing'],
    [ListingSortColumn::Status, 'Status'],
    [ListingSortColumn::Price, 'Price'],
    [ListingSortColumn::Stock, 'Stock'],
    [ListingSortColumn::Views, 'Views'],
    [ListingSortColumn::Favorites, 'Favorites'],
    [ListingSortColumn::CartAdds, 'Cart adds'],
    [ListingSortColumn::Sold, 'Sold'],
    [ListingSortColumn::Revenue, 'Revenue'],
    [ListingSortColumn::Conversion, 'Conversion'],
    [ListingSortColumn::Updated, 'Updated'],
]);

it('right-aligns every column except the listing and its status', function (ListingSortColumn $column, bool $expected): void {
    expect($column->alignsRight())->toBe($expected);
})->with([
    'title stays left' => [ListingSortColumn::Title, false],
    'status stays left' => [ListingSortColumn::Status, false],
    'price aligns right' => [ListingSortColumn::Price, true],
    'updated aligns right' => [ListingSortColumn::Updated, true],
]);

it('reads the sort key each column compares rows by', function (ListingSortColumn $column, mixed $expected) use ($row): void {
    expect($column->keyOf($row()))->toBe($expected);
})->with([
    'title lowercases' => [ListingSortColumn::Title, 'the burrow at dusk'],
    'status lowercases' => [ListingSortColumn::Status, 'live'],
    'price' => [ListingSortColumn::Price, 68000],
    'stock' => [ListingSortColumn::Stock, 1],
    'views' => [ListingSortColumn::Views, 412],
    'favorites' => [ListingSortColumn::Favorites, 31],
    'cart adds' => [ListingSortColumn::CartAdds, 14],
    'sold' => [ListingSortColumn::Sold, 1],
    'revenue' => [ListingSortColumn::Revenue, 68000],
]);

it('reads made-to-order stock as unlimited', function () use ($row): void {
    expect(ListingSortColumn::Stock->keyOf($row(quantity: null)))->toBe(PHP_INT_MAX);
});

it('reads conversion as sold over views', function () use ($row): void {
    expect(ListingSortColumn::Conversion->keyOf($row(sold: 2, views: 8)))->toBe(0.25);
});

it('reads conversion as the lowest key for a listing with no views', function () use ($row): void {
    expect(ListingSortColumn::Conversion->keyOf($row(views: 0, sold: 0)))->toBe(-1.0);
});

it('reads updated as a timestamp', function () use ($row): void {
    $updatedAt = new DateTimeImmutable('2026-08-01 09:00:00');

    expect(ListingSortColumn::Updated->keyOf($row(updatedAt: $updatedAt)))->toBe($updatedAt->getTimestamp());
});
