<?php

declare(strict_types=1);

namespace App\Actions\Listings;

use App\Domain\Listings\ListingDraft;
use App\Domain\Listings\ListingStatus;
use App\Domain\Money\Money;
use App\Models\Category;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

$draft = fn (): ListingDraft => ListingDraft::of(
    'Harbour at Dusk',
    'Oil on linen.',
    '12 x 16 in',
    Money::fromCents(24500),
    2,
);

it('writes the drafted fields', function () use ($draft): void {
    $listing = app(CreateListing::class)($this->seller(), $draft());

    expect($listing->title)->toBe('Harbour at Dusk')
        ->and($listing->description)->toBe('Oil on linen.')
        ->and($listing->dimensions)->toBe('12 x 16 in')
        ->and($listing->price_cents)->toBe(24500)
        ->and($listing->quantity)->toBe(2);
});

it('leaves a listing uncategorized by default', function () use ($draft): void {
    $listing = app(CreateListing::class)($this->seller(), $draft());

    expect($listing->category_id)->toBeNull();
});

it('categorizes a listing the seller assigned one to', function (): void {
    $category = Category::factory()->create();
    $draft = ListingDraft::of('Harbour at Dusk', 'Oil on linen.', '12 x 16 in', Money::fromCents(24500), 2, $category->id);

    $listing = app(CreateListing::class)($this->seller(), $draft);

    expect($listing->category_id)->toBe($category->id);
});

it('starts a listing as a draft', function () use ($draft): void {
    $listing = app(CreateListing::class)($this->seller(), $draft());

    expect($listing->status)->toBe(ListingStatus::Draft);
});

it('belongs to the seller that created it', function () use ($draft): void {
    $seller = $this->seller();

    $listing = app(CreateListing::class)($seller, $draft());

    expect($listing->seller_id)->toBe($seller->id);
});

it('slugs the title', function () use ($draft): void {
    $listing = app(CreateListing::class)($this->seller(), $draft());

    expect($listing->slug)->toBe('harbour-at-dusk');
});

it('numbers a slug another listing already holds', function () use ($draft): void {
    $createListing = app(CreateListing::class);
    $createListing($this->seller(), $draft());

    $second = $createListing($this->seller(), $draft());

    expect($second->slug)->toBe('harbour-at-dusk-2');
});

it('leaves a listing without an upload imageless', function () use ($draft): void {
    $listing = app(CreateListing::class)($this->seller(), $draft());

    expect($listing->image_path)->toBeNull();
});

it('stores an uploaded image on the public disk', function () use ($draft): void {
    Storage::fake('public');

    $listing = app(CreateListing::class)($this->seller(), $draft(), UploadedFile::fake()->image('harbour.jpg'));

    $imagePath = $listing->image_path;

    expect($imagePath)->not->toBeNull();
    expect($imagePath)->toStartWith('listings/');
    Storage::disk('public')->assertExists((string) $imagePath);
});
