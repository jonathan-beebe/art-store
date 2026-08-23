<?php

declare(strict_types=1);

namespace App\Actions\Listings;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('puts the upload in the listings folder of the public disk', function (): void {
    Storage::fake('public');

    $path = (new StoreListingImage)(UploadedFile::fake()->image('harbour.jpg'));

    expect($path)->toStartWith('listings/');
    Storage::disk('public')->assertExists($path);
});

it('gives two uploads of the same name separate paths', function (): void {
    Storage::fake('public');
    $storeListingImage = new StoreListingImage;

    $first = $storeListingImage(UploadedFile::fake()->image('harbour.jpg'));
    $second = $storeListingImage(UploadedFile::fake()->image('harbour.jpg'));

    expect($second)->not->toBe($first);
});

it('returns null instead of false when the disk write fails', function (): void {
    Storage::shouldReceive('disk')->with('public')->andReturnSelf();
    Storage::shouldReceive('putFile')->andReturn(false);

    $path = (new StoreListingImage)(UploadedFile::fake()->image('harbour.jpg'));

    expect($path)->toBeNull();
});
