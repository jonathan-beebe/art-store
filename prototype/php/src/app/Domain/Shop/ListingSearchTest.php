<?php

declare(strict_types=1);

namespace App\Domain\Shop;

use DomainException;

it('reads a term and a medium', function (): void {
    $search = ListingSearch::fromInput('  harbour  ', ' oil ');

    expect($search->hasTerm())->toBeTrue()
        ->and($search->hasMedium())->toBeTrue()
        ->and($search->term)->toBe('harbour')
        ->and($search->medium)->toBe('oil');
});

it('treats blank input as no filter', function (): void {
    $search = ListingSearch::fromInput('   ', null);

    expect($search->hasTerm())->toBeFalse()
        ->and($search->hasMedium())->toBeFalse();
});

it('wraps the term in wildcards', function (): void {
    expect(ListingSearch::fromInput('harbour', null)->likePattern())->toBe('%harbour%');
});

it('drops wildcards the visitor typed', function (): void {
    expect(ListingSearch::fromInput('50% _off', null)->likePattern())->toBe('%50 off%');
});

it('refuses a pattern without a term', function (): void {
    expect(fn () => ListingSearch::fromInput(null, 'oil')->likePattern())->toThrow(DomainException::class);
});
