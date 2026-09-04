<?php

declare(strict_types=1);

use App\Models\Seller;
use App\Models\StoreImage;
use App\Models\StoreProfile;
use App\Models\StoreSectionImage;

it('mints a sim_ id', function (): void {
    expect(StoreImage::factory()->create()->id)->toStartWith('sim_');
});

it('gives a default picture the same seller as the store it belongs to', function (): void {
    $image = StoreImage::factory()->create();

    expect($image->seller_id)->toBe($image->storeProfile?->seller_id);
});

it('belongs to a store and to the seller behind it', function (): void {
    $seller = Seller::factory()->create();
    $profile = StoreProfile::factory()->create(['seller_id' => $seller->id]);

    $image = StoreImage::factory()->create(['store_profile_id' => $profile->id, 'seller_id' => $seller->id]);

    expect($image->storeProfile?->id)->toBe($profile->id)
        ->and($image->seller?->id)->toBe($seller->id);
});

it('serves a same-origin url off the public disk', function (): void {
    $image = StoreImage::factory()->create(['path' => 'stores/the-orchard.jpg']);

    expect($image->url())->toBe('/storage/stores/the-orchard.jpg');
});

it('knows every gallery it has been placed in', function (): void {
    $image = StoreImage::factory()->create();
    StoreSectionImage::factory()->create(['store_image_id' => $image->id]);

    expect($image->placements()->count())->toBe(1);
});
