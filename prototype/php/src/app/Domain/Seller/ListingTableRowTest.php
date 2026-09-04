<?php

declare(strict_types=1);

namespace App\Domain\Seller;

use DateTimeImmutable;

it('reads its price and revenue as money', function (): void {
    $row = new ListingTableRow(
        id: 'lst_01',
        title: 'The Burrow at Dusk',
        imageUrl: 'https://example.test/burrow.jpg',
        medium: 'Painting',
        dimensions: '24 x 36 in',
        statusLabel: 'Live',
        statusTint: 'green',
        priceCents: 68000,
        quantity: 1,
        views: 412,
        favorites: 31,
        cartAdds: 14,
        sold: 1,
        revenueCents: 68000,
        updatedAt: new DateTimeImmutable('2026-09-01 12:00:00'),
    );

    expect($row->price()->cents)->toBe(68000)
        ->and($row->revenue()->cents)->toBe(68000);
});

it('reads its stock label off the quantity', function (?int $quantity, string $expected): void {
    $row = new ListingTableRow(
        id: 'lst_01', title: 'Piece', imageUrl: 'x', medium: null, dimensions: null,
        statusLabel: 'Live', statusTint: 'green', priceCents: 1000, quantity: $quantity,
        views: 0, favorites: 0, cartAdds: 0, sold: 0, revenueCents: 0,
        updatedAt: new DateTimeImmutable('2026-09-01 12:00:00'),
    );

    expect($row->stockLabel())->toBe($expected);
})->with([
    'a count' => [3, '3 in stock'],
    'made to order' => [null, 'Made to order'],
]);

it('computes conversion as sold over views', function (): void {
    $row = new ListingTableRow(
        id: 'lst_01', title: 'Piece', imageUrl: 'x', medium: null, dimensions: null,
        statusLabel: 'Live', statusTint: 'green', priceCents: 1000, quantity: 1,
        views: 4, favorites: 0, cartAdds: 0, sold: 1, revenueCents: 1000,
        updatedAt: new DateTimeImmutable('2026-09-01 12:00:00'),
    );

    expect($row->conversion())->toBe(0.25)
        ->and($row->conversionLabel())->toBe('25.0%');
});

it('reads conversion as null with no views', function (): void {
    $row = new ListingTableRow(
        id: 'lst_01', title: 'Piece', imageUrl: 'x', medium: null, dimensions: null,
        statusLabel: 'Draft', statusTint: 'gray', priceCents: 1000, quantity: 1,
        views: 0, favorites: 0, cartAdds: 0, sold: 0, revenueCents: 0,
        updatedAt: new DateTimeImmutable('2026-09-01 12:00:00'),
    );

    expect($row->conversion())->toBeNull()
        ->and($row->conversionLabel())->toBe('—');
});
