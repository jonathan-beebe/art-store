<?php

declare(strict_types=1);

namespace App\Domain\Listings;

use App\Domain\DomainRuleViolation;
use InvalidArgumentException;

it('resolves the quantity and status after a sale', function (
    int $currentQuantity,
    ListingStatus $currentStatus,
    int $sold,
    int $expectedQuantity,
    ListingStatus $expectedStatus,
): void {
    $stock = ListingStock::afterSale($currentQuantity, $currentStatus, $sold, 'Harbour at Dawn');

    expect($stock->quantity)->toBe($expectedQuantity)
        ->and($stock->status)->toBe($expectedStatus);
})->with([
    'a sale leaves the listing for sale while stock remains' => [3, ListingStatus::ForSale, 1, 2, ListingStatus::ForSale],
    'a sale that empties the stock marks the listing sold' => [1, ListingStatus::ForSale, 1, 0, ListingStatus::Sold],
]);

it('rejects a sale for more than the listing holds', function (): void {
    expect(fn () => ListingStock::afterSale(1, ListingStatus::ForSale, 2, 'Harbour at Dawn'))
        ->toThrow(DomainRuleViolation::class, '“Harbour at Dawn” has only 1 left.');
});

it('rejects a sale for a listing that is not for sale', function (ListingStatus $status, int $quantity): void {
    expect(fn () => ListingStock::afterSale($quantity, $status, 1, 'Harbour at Dawn'))
        ->toThrow(DomainRuleViolation::class, '“Harbour at Dawn” is no longer for sale.');
})->with([
    'a draft that was never public' => [ListingStatus::Draft, 1],
    'a listing the seller archived' => [ListingStatus::Archived, 1],
    'a listing already sold out' => [ListingStatus::Sold, 0],
]);

it('rejects a sale quantity below one', function (): void {
    expect(fn () => ListingStock::afterSale(5, ListingStatus::ForSale, 0, 'Harbour at Dawn'))->toThrow(InvalidArgumentException::class);
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

it('leaves a made-to-order quantity null through a sale', function (): void {
    $stock = ListingStock::afterSale(null, ListingStatus::ForSale, 5, 'Harbour at Dawn');

    expect($stock->quantity)->toBeNull()
        ->and($stock->status)->toBe(ListingStatus::ForSale);
});

it('leaves a made-to-order quantity null through a restock', function (): void {
    $stock = ListingStock::afterRestock(null, ListingStatus::ForSale, 2);

    expect($stock->quantity)->toBeNull();
});
