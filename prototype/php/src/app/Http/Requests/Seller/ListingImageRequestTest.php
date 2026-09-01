<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Models\ListingImage;
use Illuminate\Http\UploadedFile;

it('refuses a file type other than jpeg, png, webp, or gif', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/images", [
        'image' => UploadedFile::fake()->create('notes.txt', 10),
    ]);

    $response->assertSessionHasErrors('image');
});

it('requires an image to upload', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/images", []);

    $response->assertSessionHasErrors('image');
});

// A zero-width upload cannot be fabricated (GD refuses to build one), so the
// dimensions rule is pinned from the passing side: the 1x1 floor is accepted.
it('accepts a one-pixel image at the dimension floor', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/images", [
        'image' => UploadedFile::fake()->image('tiny.jpg', 1, 1),
    ]);

    $response->assertSessionDoesntHaveErrors('image');
});

it('refuses a file over the 5120 KB limit', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/images", [
        'image' => UploadedFile::fake()->image('huge.jpg')->size(5121),
    ]);

    $response->assertSessionHasErrors('image');
});

it('refuses a ninth image with its cap message', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    for ($position = 0; $position < ListingImage::MAX_PER_LISTING; $position++) {
        $this->listingImage($listing, ['position' => $position]);
    }

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/images", [
        'image' => UploadedFile::fake()->image('ninth.jpg'),
    ]);

    $response->assertSessionHasErrors([
        'image' => 'This listing already holds '.ListingImage::MAX_PER_LISTING.' images, the most allowed.',
    ]);
    expect(ListingImage::where('listing_id', $listing->id)->count())->toBe(ListingImage::MAX_PER_LISTING);
});
