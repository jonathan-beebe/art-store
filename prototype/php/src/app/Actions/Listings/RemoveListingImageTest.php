<?php

declare(strict_types=1);

namespace App\Actions\Listings;

use App\Models\ListingImage;
use Illuminate\Support\Facades\Storage;

it('deletes the row and the file it pointed at', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('listings/heron.png', 'contents');
    $listing = $this->listing($this->seller());
    $image = $this->listingImage($listing, ['path' => 'listings/heron.png']);

    app(RemoveListingImage::class)($image);

    expect(ListingImage::find($image->id))->toBeNull();
    Storage::disk('public')->assertMissing('listings/heron.png');
});

it('leaves other images on the listing alone', function (): void {
    Storage::fake('public');
    $listing = $this->listing($this->seller());
    $kept = $this->listingImage($listing, ['position' => 0]);
    $removed = $this->listingImage($listing, ['position' => 1]);

    app(RemoveListingImage::class)($removed);

    expect(ListingImage::find($kept->id))->not->toBeNull();
});
