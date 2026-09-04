<?php

declare(strict_types=1);

use App\Domain\Store\StoreSectionKind;
use App\Models\StoreImage;
use App\Models\StoreProfile;
use App\Models\StoreSection;
use App\Models\StoreSectionImage;

it('mints an sse_ id', function (): void {
    expect(StoreSection::factory()->create()->id)->toStartWith('sse_');
});

it('reads its kind back as the domain enum', function (): void {
    expect(StoreSection::factory()->create()->kind)->toBe(StoreSectionKind::Story)
        ->and(StoreSection::factory()->gallery()->create()->kind)->toBe(StoreSectionKind::Gallery);
});

it('belongs to the store page it is a block of', function (): void {
    $profile = StoreProfile::factory()->create();

    $section = StoreSection::factory()->create(['store_profile_id' => $profile->id]);

    expect($section->storeProfile?->id)->toBe($profile->id);
});

it('holds one section per position on a store', function (): void {
    $profile = StoreProfile::factory()->create();
    StoreSection::factory()->create(['store_profile_id' => $profile->id, 'position' => 0]);

    expect(fn () => StoreSection::factory()->create(['store_profile_id' => $profile->id, 'position' => 0]))
        ->toThrow(Illuminate\Database\UniqueConstraintViolationException::class);
});

it('places its pictures in position order', function (): void {
    $section = StoreSection::factory()->gallery()->create();
    $second = StoreSectionImage::factory()->create([
        'store_section_id' => $section->id,
        'store_image_id' => StoreImage::factory()->create()->id,
        'position' => 1,
    ]);
    $first = StoreSectionImage::factory()->create([
        'store_section_id' => $section->id,
        'store_image_id' => StoreImage::factory()->create()->id,
        'position' => 0,
    ]);

    expect($section->sectionImages()->pluck('id')->all())->toBe([$first->id, $second->id]);
});
