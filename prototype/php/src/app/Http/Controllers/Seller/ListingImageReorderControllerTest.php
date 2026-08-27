<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Domain\RateLimiting\RateLimitValue;
use Illuminate\Support\Facades\Config;

it('moves an image up', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $first = $this->listingImage($listing, ['position' => 0]);
    $second = $this->listingImage($listing, ['position' => 1]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/images/{$second->id}/reorder", [
        'direction' => 'up',
    ]);

    $response->assertRedirect(route('seller.listings.images.index', $listing));
    $response->assertSessionHas('status', 'Moved.');
    expect($second->fresh()?->position)->toBe(0)
        ->and($first->fresh()?->position)->toBe(1);
});

it('answers not found reordering an image from a different listing', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $otherListing = $this->listing($seller);
    $image = $this->listingImage($otherListing);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/images/{$image->id}/reorder", ['direction' => 'up']);

    $response->assertNotFound();
});

it('refuses reordering another sellers image', function (): void {
    $listing = $this->listing($this->seller('Other Studio'));
    $image = $this->listingImage($listing);

    $response = $this->actingAs($this->seller(), 'seller')->post("/seller/listings/{$listing->id}/images/{$image->id}/reorder", ['direction' => 'up']);

    $response->assertNotFound();
});

it('trips the listing-write limit reordering an image', function (): void {
    Config::set('rate_limits.listing_write', RateLimitValue::parse('1/1h', 'RATE_LIMIT_LISTING_WRITE'));
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $first = $this->listingImage($listing, ['position' => 0]);
    $second = $this->listingImage($listing, ['position' => 1]);
    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/images/{$second->id}/reorder", ['direction' => 'up']);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/images/{$first->id}/reorder", ['direction' => 'up']);

    $response->assertStatus(429);
});
