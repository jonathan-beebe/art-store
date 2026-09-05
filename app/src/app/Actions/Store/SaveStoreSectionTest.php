<?php

declare(strict_types=1);

use App\Actions\Store\SaveStoreSection;
use App\Models\StoreImage;
use App\Models\StoreProfile;
use App\Models\StoreSection;
use Tests\CapturedStory;

it('writes the text a section carries', function (): void {
    $section = StoreSection::factory()->create();

    app(SaveStoreSection::class)($section, 'How the Burrow makes things', 'Everything here is made in the kitchen.', []);

    $saved = $section->fresh();
    expect($saved?->heading)->toBe('How the Burrow makes things')
        ->and($saved?->body)->toBe('Everything here is made in the kitchen.');
});

it('places the pictures a gallery names, in the order they arrived', function (): void {
    $profile = StoreProfile::factory()->create();
    $section = StoreSection::factory()->gallery()->create(['store_profile_id' => $profile->id]);
    $first = StoreImage::factory()->create(['store_profile_id' => $profile->id]);
    $second = StoreImage::factory()->create(['store_profile_id' => $profile->id]);

    app(SaveStoreSection::class)($section, null, null, [$second->id, $first->id]);

    expect($section->sectionImages()->pluck('store_image_id')->all())->toBe([$second->id, $first->id])
        ->and($section->sectionImages()->pluck('position')->all())->toBe([0, 1]);
});

it('writes the placements afresh on every save', function (): void {
    $profile = StoreProfile::factory()->create();
    $section = StoreSection::factory()->gallery()->create(['store_profile_id' => $profile->id]);
    $first = StoreImage::factory()->create(['store_profile_id' => $profile->id]);
    $second = StoreImage::factory()->create(['store_profile_id' => $profile->id]);
    app(SaveStoreSection::class)($section, null, null, [$first->id, $second->id]);

    app(SaveStoreSection::class)($section, null, null, [$second->id]);

    expect($section->sectionImages()->pluck('store_image_id')->all())->toBe([$second->id]);
});

it('leaves the store holding a picture a gallery no longer places', function (): void {
    $profile = StoreProfile::factory()->create();
    $section = StoreSection::factory()->gallery()->create(['store_profile_id' => $profile->id]);
    $image = StoreImage::factory()->create(['store_profile_id' => $profile->id]);
    app(SaveStoreSection::class)($section, null, null, [$image->id]);

    app(SaveStoreSection::class)($section, null, null, []);

    expect(StoreImage::find($image->id))->not->toBeNull()
        ->and($section->sectionImages()->count())->toBe(0);
});

it('tells the story of the save', function (): void {
    $profile = StoreProfile::factory()->create();
    $section = StoreSection::factory()->create(['store_profile_id' => $profile->id]);
    $log = CapturedStory::capture();

    app(SaveStoreSection::class)($section, 'How the Burrow makes things', 'Everything here is made in the kitchen.', []);

    expect($log->values('phase', 'store.section.write'))->toBe(['will', 'did'])
        ->and($log->line('store.section.write', 'did')['data'])->toMatchArray([
            'seller_id' => $profile->seller_id,
            'store_profile_id' => $profile->id,
            'section_id' => $section->id,
            'kind' => $section->kind->value,
            'op' => 'save',
        ]);
});
