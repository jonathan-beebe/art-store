<?php

declare(strict_types=1);

namespace App\Actions\Listings;

use App\Domain\Listings\ListingImageMove;

it('swaps an image with the one before it', function (): void {
    $listing = $this->listing($this->seller());
    $first = $this->listingImage($listing, ['position' => 0]);
    $second = $this->listingImage($listing, ['position' => 1]);

    app(ReorderListingImage::class)($second, ListingImageMove::Up);

    expect($second->fresh()?->position)->toBe(0)
        ->and($first->fresh()?->position)->toBe(1);
});

it('swaps an image with the one after it', function (): void {
    $listing = $this->listing($this->seller());
    $first = $this->listingImage($listing, ['position' => 0]);
    $second = $this->listingImage($listing, ['position' => 1]);

    app(ReorderListingImage::class)($first, ListingImageMove::Down);

    expect($first->fresh()?->position)->toBe(1)
        ->and($second->fresh()?->position)->toBe(0);
});

it('does nothing moving the first image up, or the last image down', function (): void {
    $listing = $this->listing($this->seller());
    $only = $this->listingImage($listing, ['position' => 0]);

    app(ReorderListingImage::class)($only, ListingImageMove::Up);
    app(ReorderListingImage::class)($only, ListingImageMove::Down);

    expect($only->fresh()?->position)->toBe(0);
});
