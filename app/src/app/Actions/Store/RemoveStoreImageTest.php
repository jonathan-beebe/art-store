<?php

declare(strict_types=1);

use App\Actions\Store\RemoveStoreImage;
use App\Models\StoreImage;
use App\Models\StoreProfile;
use App\Models\StoreSection;
use App\Models\StoreSectionImage;
use Illuminate\Support\Facades\Storage;
use Tests\CapturedStory;

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

it('deletes its file off disk', function (): void {
    Storage::fake('public');
    $image = StoreImage::factory()->create(['path' => 'stores/portrait.jpg']);
    Storage::disk('public')->put($image->path, 'fake image bytes');

    app(RemoveStoreImage::class)($image);

    Storage::disk('public')->assertMissing($image->path);
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

it('tells the story of the removal', function (): void {
    $profile = StoreProfile::factory()->create();
    $image = StoreImage::factory()->create(['store_profile_id' => $profile->id]);
    $log = CapturedStory::capture();

    app(RemoveStoreImage::class)($image);

    expect($log->values('phase', 'store.image.write'))->toBe(['will', 'did'])
        ->and($log->line('store.image.write', 'did')['data'])->toMatchArray([
            'seller_id' => $profile->seller_id,
            'store_profile_id' => $profile->id,
            'image_id' => $image->id,
            'op' => 'remove',
        ]);
});
