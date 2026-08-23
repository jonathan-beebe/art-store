<?php

declare(strict_types=1);

namespace App\Domain\Listings;

use DomainException;
use InvalidArgumentException;

it('resolves the quantity and status after a sale', function (
    int $currentQuantity,
    ListingStatus $currentStatus,
    int $sold,
    int $expectedQuantity,
    ListingStatus $expectedStatus,
): void {
    $stock = ListingStock::afterSale($currentQuantity, $currentStatus, $sold);

    expect($stock->quantity)->toBe($expectedQuantity)
        ->and($stock->status)->toBe($expectedStatus);
})->with([
    'a sale leaves the listing for sale while stock remains' => [3, ListingStatus::ForSale, 1, 2, ListingStatus::ForSale],
    'a sale that empties the stock marks the listing sold' => [1, ListingStatus::ForSale, 1, 0, ListingStatus::Sold],
]);

it('rejects a sale for more than the listing holds', function (): void {
    expect(fn () => ListingStock::afterSale(1, ListingStatus::ForSale, 2))->toThrow(DomainException::class);
});

it('rejects a sale for a listing that is not for sale', function (): void {
    expect(fn () => ListingStock::afterSale(1, ListingStatus::Draft, 1))->toThrow(DomainException::class);
});

it('rejects a sale quantity below one', function (): void {
    expect(fn () => ListingStock::afterSale(5, ListingStatus::ForSale, 0))->toThrow(InvalidArgumentException::class);
});

it('resolves the quantity and status after a restock', function (
    int $currentQuantity,
    ListingStatus $currentStatus,
    int $restocked,
    int $expectedQuantity,
    ListingStatus $expectedStatus,
): void {
    $stock = ListingStock::afterRestock($currentQuantity, $currentStatus, $restocked);

    expect($stock->quantity)->toBe($expectedQuantity)
        ->and($stock->status)->toBe($expectedStatus);
})->with([
    'a restock puts a sold listing back up for sale' => [0, ListingStatus::Sold, 1, 1, ListingStatus::ForSale],
    'a restock leaves a listing that never sold out untouched' => [2, ListingStatus::ForSale, 1, 3, ListingStatus::ForSale],
]);

it('leaves an archived listing archived after a restock', function (): void {
    expect(ListingStock::afterRestock(0, ListingStatus::Archived, 1)->status)->toBe(ListingStatus::Archived);
});

it('rejects a restock quantity below one', function (): void {
    expect(fn () => ListingStock::afterRestock(0, ListingStatus::Sold, 0))->toThrow(InvalidArgumentException::class);
});
