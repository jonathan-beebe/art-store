<?php

declare(strict_types=1);

use App\Models\StoreImage;
use App\Models\StoreSection;
use App\Models\StoreSectionImage;

it('mints an ssi_ id', function (): void {
    expect(StoreSectionImage::factory()->create()->id)->toStartWith('ssi_');
});

it('gives a default placement a picture belonging to its own section\'s store', function (): void {
    $placement = StoreSectionImage::factory()->create();

    expect($placement->storeImage?->store_profile_id)->toBe($placement->storeSection?->store_profile_id);
});

it('names the section it places a picture in and the picture it places', function (): void {
    $section = StoreSection::factory()->gallery()->create();
    $image = StoreImage::factory()->create();

    $placement = StoreSectionImage::factory()->create([
        'store_section_id' => $section->id,
        'store_image_id' => $image->id,
    ]);

    expect($placement->storeSection?->id)->toBe($section->id)
        ->and($placement->storeImage?->id)->toBe($image->id);
});

it('holds one picture per position in a gallery', function (): void {
    $section = StoreSection::factory()->gallery()->create();
    StoreSectionImage::factory()->create(['store_section_id' => $section->id, 'position' => 0]);

    expect(fn () => StoreSectionImage::factory()->create(['store_section_id' => $section->id, 'position' => 0]))
        ->toThrow(Illuminate\Database\UniqueConstraintViolationException::class);
});

it('places one picture at most once in a gallery', function (): void {
    $section = StoreSection::factory()->gallery()->create();
    $image = StoreImage::factory()->create();
    StoreSectionImage::factory()->create([
        'store_section_id' => $section->id,
        'store_image_id' => $image->id,
        'position' => 0,
    ]);

    expect(fn () => StoreSectionImage::factory()->create([
        'store_section_id' => $section->id,
        'store_image_id' => $image->id,
        'position' => 1,
    ]))->toThrow(Illuminate\Database\UniqueConstraintViolationException::class);
});
