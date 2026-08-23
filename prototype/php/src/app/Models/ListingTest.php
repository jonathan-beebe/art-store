<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Listings\ListingStatus;

it('surfaces only listings for sale on the storefront', function (): void {
    $seller = $this->seller();
    $forSale = $this->listing($seller);
    $this->listing($seller, ['status' => ListingStatus::Draft]);
    $this->listing($seller, ['status' => ListingStatus::Sold, 'quantity' => 0]);

    expect(Listing::query()->forSale()->pluck('id')->all())->toBe([$forSale->id]);
});

it('reads whether it can still be bought', function (): void {
    $seller = $this->seller();

    expect($this->listing($seller)->isPurchasable())->toBeTrue()
        ->and($this->listing($seller, ['status' => ListingStatus::Archived])->isPurchasable())->toBeFalse()
        ->and($this->listing($seller, ['status' => ListingStatus::ForSale, 'quantity' => 0])->isPurchasable())->toBeFalse();
});

it('reads its price as money', function (): void {
    $listing = $this->listing($this->seller(), ['price_cents' => 45000]);

    expect($listing->price()->format())->toBe('$450.00');
});

it('renders a placeholder image when there is no upload', function (): void {
    $listing = $this->listing($this->seller(), ['title' => 'Blue Heron', 'image_path' => null]);

    expect($listing->imageUrl())->toStartWith('data:image/svg+xml;base64,');
});

it('serves an uploaded image from the public disk', function (): void {
    $listing = $this->listing($this->seller(), ['image_path' => 'listings/heron.png']);

    expect($listing->imageUrl())->toEndWith('/storage/listings/heron.png');
});
