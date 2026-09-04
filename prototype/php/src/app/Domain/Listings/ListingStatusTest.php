<?php

declare(strict_types=1);

namespace App\Domain\Listings;

use DomainException;

it('lists the statuses a status may transition to', function (ListingStatus $from, array $expected): void {
    expect($from->transitions())->toBe($expected);
})->with([
    'a draft may be put up for sale or archived' => [ListingStatus::Draft, [ListingStatus::ForSale, ListingStatus::Archived]],
    'a for-sale listing may sell out or be archived' => [ListingStatus::ForSale, [ListingStatus::Sold, ListingStatus::Archived]],
    'a sold listing returns to sale when stock comes back' => [ListingStatus::Sold, [ListingStatus::ForSale]],
    'an archived listing is final' => [ListingStatus::Archived, []],
]);

it('agrees with the transition table on every pair', function (): void {
    foreach (ListingStatus::cases() as $from) {
        foreach (ListingStatus::cases() as $to) {
            expect($from->canTransitionTo($to))
                ->toBe(in_array($to, $from->transitions(), true), "{$from->value} -> {$to->value}");
        }
    }
});

it('returns the next status on transition', function (): void {
    expect(ListingStatus::Draft->transitionTo(ListingStatus::ForSale))->toBe(ListingStatus::ForSale);
});

it('rejects a move outside the table', function (): void {
    expect(fn () => ListingStatus::Draft->transitionTo(ListingStatus::Sold))
        ->toThrow(DomainException::class, 'draft to sold');
});

it('is on the storefront only when for sale or sold', function (ListingStatus $status, bool $expected): void {
    expect($status->isOnStorefront())->toBe($expected);
})->with([
    'for sale has a page' => [ListingStatus::ForSale, true],
    'sold has a page' => [ListingStatus::Sold, true],
    'draft has no page' => [ListingStatus::Draft, false],
    'archived has no page' => [ListingStatus::Archived, false],
]);

it('reads its stored value back as a sentence', function (ListingStatus $status, string $expected): void {
    expect($status->label())->toBe($expected);
})->with([
    'draft' => [ListingStatus::Draft, 'Draft'],
    'for sale' => [ListingStatus::ForSale, 'For sale'],
    'sold' => [ListingStatus::Sold, 'Sold'],
    'archived' => [ListingStatus::Archived, 'Archived'],
]);

it('reads the seller badge label off status and removal', function (ListingStatus $status, bool $removed, string $expected): void {
    expect($status->sellerBadgeLabel($removed))->toBe($expected);
})->with([
    'draft reads draft' => [ListingStatus::Draft, false, 'Draft'],
    'for sale reads live' => [ListingStatus::ForSale, false, 'Live'],
    'sold reads sold out' => [ListingStatus::Sold, false, 'Sold out'],
    'archived reads removed' => [ListingStatus::Archived, false, 'Removed'],
    'an active removal outranks for sale' => [ListingStatus::ForSale, true, 'Removed'],
    'an active removal outranks draft' => [ListingStatus::Draft, true, 'Removed'],
]);

it('reads the seller badge tint off status and removal', function (ListingStatus $status, bool $removed, string $expected): void {
    expect($status->sellerBadgeTint($removed))->toBe($expected);
})->with([
    'draft is gray' => [ListingStatus::Draft, false, 'gray'],
    'for sale is green' => [ListingStatus::ForSale, false, 'green'],
    'sold is red' => [ListingStatus::Sold, false, 'red'],
    'archived is red' => [ListingStatus::Archived, false, 'red'],
    'an active removal is red' => [ListingStatus::ForSale, true, 'red'],
]);
