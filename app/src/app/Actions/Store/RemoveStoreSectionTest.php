<?php

declare(strict_types=1);

use App\Actions\Store\RemoveStoreSection;
use App\Models\StoreImage;
use App\Models\StoreProfile;
use App\Models\StoreSection;
use App\Models\StoreSectionImage;
use Tests\CapturedStory;

it('takes the section off the page', function (): void {
    $section = StoreSection::factory()->create();

    app(RemoveStoreSection::class)($section);

    expect(StoreSection::find($section->id))->toBeNull();
});

it('takes the gallery placements with it and leaves the pictures', function (): void {
    $profile = StoreProfile::factory()->create();
    $section = StoreSection::factory()->gallery()->create(['store_profile_id' => $profile->id]);
    $image = StoreImage::factory()->create(['store_profile_id' => $profile->id]);
    StoreSectionImage::factory()->create(['store_section_id' => $section->id, 'store_image_id' => $image->id]);

    app(RemoveStoreSection::class)($section);

    expect(StoreSectionImage::count())->toBe(0)
        ->and(StoreImage::find($image->id))->not->toBeNull();
});

it('leaves the store\'s other sections alone', function (): void {
    $profile = StoreProfile::factory()->create();
    $kept = StoreSection::factory()->create(['store_profile_id' => $profile->id, 'position' => 0]);
    $removed = StoreSection::factory()->gallery()->create(['store_profile_id' => $profile->id, 'position' => 1]);

    app(RemoveStoreSection::class)($removed);

    expect($profile->sections()->pluck('id')->all())->toBe([$kept->id]);
});

it('tells the story of the removal', function (): void {
    $profile = StoreProfile::factory()->create();
    $section = StoreSection::factory()->create(['store_profile_id' => $profile->id]);
    $log = CapturedStory::capture();

    app(RemoveStoreSection::class)($section);

    expect($log->values('phase', 'store.section.write'))->toBe(['will', 'did'])
        ->and($log->line('store.section.write', 'did')['data'])->toMatchArray([
            'seller_id' => $profile->seller_id,
            'store_profile_id' => $profile->id,
            'section_id' => $section->id,
            'kind' => $section->kind->value,
            'op' => 'remove',
        ]);
});
