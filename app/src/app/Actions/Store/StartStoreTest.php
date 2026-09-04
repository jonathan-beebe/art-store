<?php

declare(strict_types=1);

use App\Actions\Store\StartStore;
use App\Models\Seller;
use App\Models\StoreProfile;
use App\Models\StoreSlug;

it('gives a seller with no store a hidden one named after their shop', function (): void {
    $seller = Seller::factory()->create(['shop_name' => 'The Burrow Craftworks']);

    $profile = app(StartStore::class)($seller);

    expect($profile->name)->toBe('The Burrow Craftworks')
        ->and($profile->slug)->toBe('the-burrow-craftworks')
        ->and($profile->isPublished())->toBeFalse()
        ->and($profile->seller_id)->toBe($seller->id);
});

it('records the address the new store answers to', function (): void {
    $seller = Seller::factory()->create(['shop_name' => 'The Burrow Craftworks']);

    $profile = app(StartStore::class)($seller);

    $slug = $profile->slugs()->sole();
    expect($slug->slug)->toBe('the-burrow-craftworks')
        ->and($slug->isRetired())->toBeFalse();
});

it('hands back the store a seller already has', function (): void {
    $seller = Seller::factory()->create();
    $existing = StoreProfile::factory()->create(['seller_id' => $seller->id]);

    $profile = app(StartStore::class)($seller);

    expect($profile->id)->toBe($existing->id)
        ->and(StoreProfile::count())->toBe(1);
});

it('counts past an address another store already answers to', function (): void {
    StoreSlug::factory()->create(['slug' => 'the-burrow-craftworks']);
    $seller = Seller::factory()->create(['shop_name' => 'The Burrow Craftworks']);

    $profile = app(StartStore::class)($seller);

    expect($profile->slug)->toBe('the-burrow-craftworks-2');
});

it('counts past an address a store retired', function (): void {
    StoreSlug::factory()->retired()->create(['slug' => 'the-burrow-craftworks']);
    $seller = Seller::factory()->create(['shop_name' => 'The Burrow Craftworks']);

    $profile = app(StartStore::class)($seller);

    expect($profile->slug)->toBe('the-burrow-craftworks-2');
});
