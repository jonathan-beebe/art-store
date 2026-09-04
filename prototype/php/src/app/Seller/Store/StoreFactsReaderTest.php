<?php

declare(strict_types=1);

namespace App\Seller\Store;

use App\Domain\Listings\ListingStatus;
use App\Models\Seller;
use App\Models\StoreProfile;
use DateTimeImmutable;

it('counts only what a buyer can still buy from this seller', function (): void {
    $seller = $this->seller();
    $profile = StoreProfile::factory()->create(['seller_id' => $seller->id]);
    $this->listing($seller, ['status' => ListingStatus::ForSale]);
    $this->listing($seller, ['status' => ListingStatus::Sold]);
    $this->listing($seller, ['status' => ListingStatus::Draft]);
    $this->listing($seller, ['status' => ListingStatus::Archived]);
    $this->listing($this->seller('Other Studio'), ['status' => ListingStatus::ForSale]);

    expect(StoreFactsReader::for($profile)->pieceCount)->toBe(1);
});

it('reads the selling-since date from the seller\'s verification', function (): void {
    $seller = Seller::factory()->create(['email_verified_at' => new DateTimeImmutable('2026-03-14')]);
    $profile = StoreProfile::factory()->create(['seller_id' => $seller->id]);

    expect(StoreFactsReader::for($profile)->sellingSince?->format('F Y'))->toBe('March 2026');
});

it('falls back to the account creation date for an unverified seller', function (): void {
    $seller = Seller::factory()->unverified()->create();
    $profile = StoreProfile::factory()->create(['seller_id' => $seller->id]);

    $sellingSince = StoreFactsReader::for($profile)->sellingSince;
    $createdAt = $seller->created_at;

    expect($sellingSince)->not->toBeNull()
        ->and($createdAt)->not->toBeNull();

    if ($sellingSince instanceof DateTimeImmutable && $createdAt !== null) {
        expect($sellingSince->format('F Y'))->toBe($createdAt->format('F Y'));
    }
});
