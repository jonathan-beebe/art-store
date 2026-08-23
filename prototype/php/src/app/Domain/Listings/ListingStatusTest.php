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
