<?php

declare(strict_types=1);

namespace App\Domain\Listings;

it('slugs the title', function (): void {
    expect(ListingSlug::firstFree('Harbour at Dusk', []))->toBe('harbour-at-dusk');
});

it('numbers a slug another listing already holds', function (): void {
    expect(ListingSlug::firstFree('Harbour at Dusk', ['harbour-at-dusk']))->toBe('harbour-at-dusk-2');
});

it('keeps counting past a numbered slug', function (): void {
    $taken = ['harbour-at-dusk', 'harbour-at-dusk-2', 'harbour-at-dusk-3'];

    expect(ListingSlug::firstFree('Harbour at Dusk', $taken))->toBe('harbour-at-dusk-4');
});

it('falls back to a word when the title slugs to nothing', function (): void {
    expect(ListingSlug::firstFree('—', []))->toBe('listing')
        ->and(ListingSlug::base('—'))->toBe('listing');
});

it('ignores what is already taken for its base', function (): void {
    expect(ListingSlug::base('Harbour at Dusk'))->toBe('harbour-at-dusk');
});

it('transliterates accented characters to their plain ascii letter', function (): void {
    expect(ListingSlug::base('Café Élan'))->toBe('cafe-elan');
});
