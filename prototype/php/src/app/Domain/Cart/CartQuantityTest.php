<?php

declare(strict_types=1);

namespace App\Domain\Cart;

use App\Domain\DomainRuleViolation;
use App\Domain\Listings\ListingStatus;
use InvalidArgumentException;

it('keeps a quantity within stock', function (int $requested, int $stock, int $expected): void {
    expect(CartQuantity::withinStock($requested, $stock, ListingStatus::ForSale, hasActiveRemoval: false))->toBe($expected);
})->with([
    'a quantity the listing can cover' => [2, 5, 2],
    'a quantity capped at the stock on hand' => [9, 5, 5],
]);

it('rejects a listing that cannot be sold', function (?int $available, ListingStatus $status, bool $hasActiveRemoval): void {
    expect(fn () => CartQuantity::withinStock(1, $available, $status, $hasActiveRemoval))
        ->toThrow(DomainRuleViolation::class, 'That listing is no longer for sale.');
})->with([
    'nothing left in stock' => [0, ListingStatus::ForSale, false],
    'a draft that was never public' => [5, ListingStatus::Draft, false],
    'a listing the seller archived' => [5, ListingStatus::Archived, false],
    'a listing an admin has removed from the storefront' => [5, ListingStatus::ForSale, true],
]);

it('rejects a request below one', function (): void {
    expect(fn () => CartQuantity::withinStock(0, 5, ListingStatus::ForSale, hasActiveRemoval: false))->toThrow(InvalidArgumentException::class);
});

it('leaves a made-to-order request uncapped', function (): void {
    expect(CartQuantity::withinStock(9, null, ListingStatus::ForSale, hasActiveRemoval: false))->toBe(9);
});
