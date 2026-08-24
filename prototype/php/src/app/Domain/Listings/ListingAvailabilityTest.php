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

it('is on the storefront only when the status allows it and no removal stands', function (ListingStatus $status, bool $hasActiveRemoval, bool $expected): void {
    expect(ListingAvailability::isOnStorefront($status, $hasActiveRemoval))->toBe($expected);
})->with([
    'for sale, no removal' => [ListingStatus::ForSale, false, true],
    'sold, no removal' => [ListingStatus::Sold, false, true],
    'draft, no removal' => [ListingStatus::Draft, false, false],
    'archived, no removal' => [ListingStatus::Archived, false, false],
    'for sale, removed' => [ListingStatus::ForSale, true, false],
    'sold, removed' => [ListingStatus::Sold, true, false],
    'draft, removed' => [ListingStatus::Draft, true, false],
]);

it('drops for_sale from the available transitions while a removal stands', function (): void {
    expect(ListingAvailability::availableTransitions(ListingStatus::Sold, true))->toBe([])
        ->and(ListingAvailability::availableTransitions(ListingStatus::Sold, false))->toBe([ListingStatus::ForSale])
        ->and(ListingAvailability::availableTransitions(ListingStatus::Draft, true))->toBe([ListingStatus::Archived])
        ->and(ListingAvailability::availableTransitions(ListingStatus::Draft, false))->toBe([ListingStatus::ForSale, ListingStatus::Archived]);
});

it('leaves a transition table with no for_sale in it untouched by a removal', function (): void {
    expect(ListingAvailability::availableTransitions(ListingStatus::Archived, true))->toBe([])
        ->and(ListingAvailability::availableTransitions(ListingStatus::ForSale, true))->toBe([ListingStatus::Sold, ListingStatus::Archived]);
});
