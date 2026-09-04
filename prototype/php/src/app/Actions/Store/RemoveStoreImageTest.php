<?php

declare(strict_types=1);

use App\Actions\Store\RemoveStoreImage;
use App\Models\StoreImage;
use App\Models\StoreProfile;
use App\Models\StoreSection;
use App\Models\StoreSectionImage;

it('takes the picture out of the store', function (): void {
    $image = StoreImage::factory()->create();

    app(RemoveStoreImage::class)($image);

    expect(StoreImage::find($image->id))->toBeNull();
});

it('clears a profile column that pointed at it', function (): void {
    $profile = StoreProfile::factory()->create();
    $portrait = StoreImage::factory()->create(['store_profile_id' => $profile->id]);
    $profile->update(['portrait_image_id' => $portrait->id, 'cover_image_id' => $portrait->id]);

    app(RemoveStoreImage::class)($portrait);

    $saved = $profile->fresh();
    expect($saved?->portrait_image_id)->toBeNull()
        ->and($saved?->cover_image_id)->toBeNull();
});

it('leaves a column pointing at another picture alone', function (): void {
    $profile = StoreProfile::factory()->create();
    $portrait = StoreImage::factory()->create(['store_profile_id' => $profile->id]);
    $cover = StoreImage::factory()->create(['store_profile_id' => $profile->id]);
    $profile->update(['portrait_image_id' => $portrait->id, 'cover_image_id' => $cover->id]);

    app(RemoveStoreImage::class)($portrait);

    expect($profile->fresh()?->cover_image_id)->toBe($cover->id);
});

it('takes the gallery placements that named it with it', function (): void {
    $profile = StoreProfile::factory()->create();
    $section = StoreSection::factory()->gallery()->create(['store_profile_id' => $profile->id]);
    $image = StoreImage::factory()->create(['store_profile_id' => $profile->id]);
    StoreSectionImage::factory()->create(['store_section_id' => $section->id, 'store_image_id' => $image->id]);

    app(RemoveStoreImage::class)($image);

    expect(StoreSectionImage::count())->toBe(0)
        ->and(StoreSection::find($section->id))->not->toBeNull();
});
