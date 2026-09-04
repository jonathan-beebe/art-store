<?php

declare(strict_types=1);

namespace App\Actions\Listings;

use App\Models\ListingImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('appends the first image at position zero', function (): void {
    Storage::fake('public');
    $listing = $this->listing($this->seller());

    $image = app(AddListingImage::class)($listing, UploadedFile::fake()->image('harbour.jpg'));

    expect($image?->listing_id)->toBe($listing->id)
        ->and($image?->position)->toBe(0);
    Storage::disk('public')->assertExists((string) $image?->path);
});

it('appends past the highest existing position', function (): void {
    Storage::fake('public');
    $listing = $this->listing($this->seller());
    $this->listingImage($listing, ['position' => 0]);
    $this->listingImage($listing, ['position' => 1]);

    $image = app(AddListingImage::class)($listing, UploadedFile::fake()->image('harbour.jpg'));

    expect($image?->position)->toBe(2);
});

it('leaves the listing untouched when the disk write fails', function (): void {
    Storage::shouldReceive('disk')->with('public')->andReturnSelf();
    Storage::shouldReceive('putFile')->andReturn(false);
    $listing = $this->listing($this->seller());

    $image = app(AddListingImage::class)($listing, UploadedFile::fake()->image('harbour.jpg'));

    expect($image)->toBeNull();
    expect(ListingImage::where('listing_id', $listing->id)->count())->toBe(0);
});
