<?php

declare(strict_types=1);

namespace App\Domain\Listings;

it('is purchasable only when for sale with stock', function (ListingStatus $status, int $stock, bool $expected): void {
    expect(ListingAvailability::isPurchasable($status, $stock))->toBe($expected);
})->with([
    'for sale with stock can be bought' => [ListingStatus::ForSale, 1, true],
    'for sale without stock cannot be bought' => [ListingStatus::ForSale, 0, false],
    'sold cannot be bought' => [ListingStatus::Sold, 1, false],
    'draft cannot be bought' => [ListingStatus::Draft, 3, false],
    'archived cannot be bought' => [ListingStatus::Archived, 3, false],
]);

it('is on the storefront only when for sale or sold', function (ListingStatus $status, bool $expected): void {
    expect(ListingAvailability::isOnStorefront($status))->toBe($expected);
})->with([
    'for sale has a page' => [ListingStatus::ForSale, true],
    'sold has a page' => [ListingStatus::Sold, true],
    'draft has no page' => [ListingStatus::Draft, false],
    'archived has no page' => [ListingStatus::Archived, false],
]);
