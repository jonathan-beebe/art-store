<?php

declare(strict_types=1);

use App\Domain\Listings\ListingStatus;
use App\Domain\Store\StoreSectionKind;
use App\Models\Listing;
use App\Models\StoreImage;
use App\Models\StoreProfile;
use App\Models\StoreSection;
use App\Seller\Store\StoreFacts;
use App\Seller\Store\StorefrontStorePageData;

it('hands the page everything the shared component reads', function (): void {
    $profile = StoreProfile::factory()->create();

    $page = StorefrontStorePageData::build($profile, false);

    expect($page->facts)->toBeInstanceOf(StoreFacts::class)
        ->and($profile->relationLoaded('sections'))->toBeTrue()
        ->and($profile->relationLoaded('links'))->toBeTrue()
        ->and($profile->relationLoaded('portraitImage'))->toBeTrue()
        ->and($profile->relationLoaded('coverImage'))->toBeTrue();
});

it('pages only the maker\'s storefront listings', function (): void {
    $profile = StoreProfile::factory()->create();
    $forSale = Listing::factory()->create(['seller_id' => $profile->seller_id, 'status' => ListingStatus::ForSale]);
    Listing::factory()->create(['seller_id' => $profile->seller_id, 'status' => ListingStatus::Draft]);
    Listing::factory()->create(['status' => ListingStatus::ForSale]);

    $listings = StorefrontStorePageData::build($profile, false)->listings;

    expect(collect($listings->items())->pluck('id')->all())->toBe([$forSale->id]);
});

it('describes the store by its tagline', function (): void {
    $profile = StoreProfile::factory()->create(['tagline' => 'Knitted, thrown, and carved at the Burrow']);

    expect(StorefrontStorePageData::build($profile, false)->description)
        ->toBe('Knitted, thrown, and carved at the Burrow');
});

it('falls back to the opening of the story when there is no tagline', function (): void {
    $profile = StoreProfile::factory()->create(['tagline' => null]);
    StoreSection::factory()->create([
        'store_profile_id' => $profile->id,
        'kind' => StoreSectionKind::Story,
        'body' => 'Everything here is made in the kitchen, the shed, or the orchard.',
    ]);

    expect(StorefrontStorePageData::build($profile, false)->description)
        ->toBe('Everything here is made in the kitchen, the shed, or the orchard.');
});

it('falls back to the name when the seller has written neither', function (): void {
    $profile = StoreProfile::factory()->create(['tagline' => null, 'name' => 'Nine Owls']);

    expect(StorefrontStorePageData::build($profile, false)->description)->toBe('Nine Owls');
});

it('offers the cover as the link preview picture, then the portrait', function (): void {
    $profile = StoreProfile::factory()->create();
    $portrait = StoreImage::factory()->create(['store_profile_id' => $profile->id, 'path' => 'stores/me.jpg']);
    $profile->update(['portrait_image_id' => $portrait->id]);

    expect(StorefrontStorePageData::build($profile->refresh(), false)->ogImage)->toBe('/storage/stores/me.jpg');

    $cover = StoreImage::factory()->create(['store_profile_id' => $profile->id, 'path' => 'stores/orchard.jpg']);
    $profile->update(['cover_image_id' => $cover->id]);

    expect(StorefrontStorePageData::build($profile->refresh(), false)->ogImage)->toBe('/storage/stores/orchard.jpg');
});

it('offers no picture for a store with neither', function (): void {
    expect(StorefrontStorePageData::build(StoreProfile::factory()->create(), false)->ogImage)->toBeNull();
});
