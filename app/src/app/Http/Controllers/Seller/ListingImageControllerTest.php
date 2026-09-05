<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Domain\RateLimiting\RateLimitValue;
use App\Models\ListingImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

it('lists the images cover-first with the cover tagged', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $this->listingImage($listing, ['position' => 1]);
    $this->listingImage($listing, ['position' => 0]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/images");

    $response->assertOk();
    $response->assertSeeInOrder(['Cover']);
});

it('adds an uploaded image at the end', function (): void {
    Storage::fake('public');
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $this->listingImage($listing, ['position' => 0]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/images", [
        'image' => UploadedFile::fake()->image('second.jpg'),
    ]);

    $response->assertRedirect(route('seller.listings.images.index', $listing));
    $response->assertSessionHas('status', 'Image added.');
    expect(ListingImage::where('listing_id', $listing->id)->where('position', 1)->exists())->toBeTrue();
});

it('tells the seller when the upload fails', function (): void {
    Storage::shouldReceive('disk')->with('public')->andReturnSelf();
    Storage::shouldReceive('putFile')->andReturn(false);
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/images", [
        'image' => UploadedFile::fake()->image('second.jpg'),
    ]);

    $response->assertSessionHas('status', fn (string $status): bool => str_contains($status, 'failed to upload'));
});

it('refuses a ninth image with a craft-worded cap message', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    for ($position = 0; $position < ListingImage::MAX_PER_LISTING; $position++) {
        $this->listingImage($listing, ['position' => $position]);
    }

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/images", [
        'image' => UploadedFile::fake()->image('ninth.jpg'),
    ]);

    $response->assertSessionHasErrors('image');
    expect(ListingImage::where('listing_id', $listing->id)->count())->toBe(ListingImage::MAX_PER_LISTING);
});

it('removes an image', function (): void {
    Storage::fake('public');
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $image = $this->listingImage($listing);

    $response = $this->actingAs($seller, 'seller')->delete("/seller/listings/{$listing->id}/images/{$image->id}");

    $response->assertRedirect(route('seller.listings.images.index', $listing));
    $response->assertSessionHas('status', 'Image removed.');
    expect(ListingImage::find($image->id))->toBeNull();
});

it('trips the listing-write limit adding an image', function (): void {
    Storage::fake('public');
    Config::set('rate_limits.listing_write', RateLimitValue::parse('1/1h', 'RATE_LIMIT_LISTING_WRITE'));
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/images", [
        'image' => UploadedFile::fake()->image('first.jpg'),
    ]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/images", [
        'image' => UploadedFile::fake()->image('second.jpg'),
    ]);

    $response->assertStatus(429);
    expect(ListingImage::where('listing_id', $listing->id)->count())->toBe(1);
});

it('trips the listing-write limit removing an image', function (): void {
    Storage::fake('public');
    Config::set('rate_limits.listing_write', RateLimitValue::parse('1/1h', 'RATE_LIMIT_LISTING_WRITE'));
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $image = $this->listingImage($listing);
    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/images", [
        'image' => UploadedFile::fake()->image('second.jpg'),
    ]);

    $response = $this->actingAs($seller, 'seller')->delete("/seller/listings/{$listing->id}/images/{$image->id}");

    $response->assertStatus(429);
    expect(ListingImage::find($image->id))->not->toBeNull();
});
