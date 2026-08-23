<?php

declare(strict_types=1);

namespace App\Actions\Listings;

use App\Domain\Listings\ListingDraft;
use App\Domain\Listings\ListingStatus;
use App\Domain\Money\Money;
use App\Models\Listing;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

$draft = fn (): ListingDraft => ListingDraft::of(
    'Harbour at Dawn',
    'Oil on linen.',
    'oil',
    '12 x 16 in',
    Money::fromCents(9900),
    1,
);

it('writes the drafted fields', function () use ($draft): void {
    $listing = $this->listing($this->seller(), ['title' => 'Old title', 'price_cents' => 100]);

    app(UpdateListing::class)($listing, $draft());

    expect($listing->refresh()->title)->toBe('Harbour at Dawn')
        ->and($listing->price_cents)->toBe(9900);
});

it('keeps the slug a renamed listing was shared under', function () use ($draft): void {
    $listing = $this->listing($this->seller(), ['title' => 'Old title', 'slug' => 'old-title']);

    app(UpdateListing::class)($listing, $draft());

    expect($listing->refresh()->slug)->toBe('old-title');
});

it('keeps the status the listing already had', function () use ($draft): void {
    $listing = $this->listing($this->seller(), ['status' => ListingStatus::ForSale]);

    app(UpdateListing::class)($listing, $draft());

    expect($listing)->toHaveStatus(ListingStatus::ForSale);
});

it('keeps the image when the form uploads none', function () use ($draft): void {
    $listing = $this->listing($this->seller(), ['image_path' => 'listings/kept.jpg']);

    app(UpdateListing::class)($listing, $draft());

    expect($listing->refresh()->image_path)->toBe('listings/kept.jpg');
});

it('replaces the image and deletes the file it replaced', function () use ($draft): void {
    Storage::fake('public');
    Storage::disk('public')->put('listings/old.jpg', 'old');
    $listing = $this->listing($this->seller(), ['image_path' => 'listings/old.jpg']);

    app(UpdateListing::class)($listing, $draft(), UploadedFile::fake()->image('new.jpg'));

    $imagePath = $listing->refresh()->image_path;

    expect($imagePath)->not->toBeNull();
    expect($imagePath)->not->toBe('listings/old.jpg');
    Storage::disk('public')->assertMissing('listings/old.jpg');
    Storage::disk('public')->assertExists((string) $imagePath);
});

it('keeps the previous image and does not delete it when the write fails', function () use ($draft): void {
    $listing = $this->listing($this->seller(), ['image_path' => 'listings/old.jpg']);
    Storage::shouldReceive('disk')->with('public')->andReturnSelf();
    Storage::shouldReceive('putFile')->andReturn(false);
    Storage::shouldReceive('delete')->never();

    app(UpdateListing::class)($listing, $draft(), UploadedFile::fake()->image('new.jpg'));

    expect($listing->refresh()->image_path)->toBe('listings/old.jpg');
});

it('leaves other listings alone', function () use ($draft): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['title' => 'Mine']);
    $other = $this->listing($seller, ['title' => 'Untouched']);

    app(UpdateListing::class)($listing, $draft());

    expect(Listing::findOrFail($other->id)->title)->toBe('Untouched');
});
