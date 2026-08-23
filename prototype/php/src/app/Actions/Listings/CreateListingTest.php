<?php

declare(strict_types=1);

namespace App\Actions\Listings;

use App\Domain\Listings\ListingDraft;
use App\Domain\Listings\ListingStatus;
use App\Domain\Money\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

$draft = fn (): ListingDraft => ListingDraft::of(
    'Harbour at Dusk',
    'Oil on linen.',
    'oil',
    '12 x 16 in',
    Money::fromCents(24500),
    2,
);

it('writes the drafted fields', function () use ($draft): void {
    $listing = app(CreateListing::class)($this->seller(), $draft());

    expect($listing)
        ->title->toBe('Harbour at Dusk')
        ->description->toBe('Oil on linen.')
        ->medium->toBe('oil')
        ->dimensions->toBe('12 x 16 in')
        ->price_cents->toBe(24500)
        ->quantity->toBe(2);
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

    expect($listing->image_path)->not->toBeNull();
    expect($listing->image_path)->toStartWith('listings/');
    Storage::disk('public')->assertExists($listing->image_path);
});
