<?php

declare(strict_types=1);

namespace App\Actions\Listings;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class StoreListingImageTest extends TestCase
{
    public function test_it_puts_the_upload_in_the_listings_folder_of_the_public_disk(): void
    {
        Storage::fake('public');

        $path = (new StoreListingImage)(UploadedFile::fake()->image('harbour.jpg'));

        $this->assertStringStartsWith('listings/', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_it_gives_two_uploads_of_the_same_name_separate_paths(): void
    {
        Storage::fake('public');
        $storeListingImage = new StoreListingImage;

        $first = $storeListingImage(UploadedFile::fake()->image('harbour.jpg'));
        $second = $storeListingImage(UploadedFile::fake()->image('harbour.jpg'));

        $this->assertNotSame($first, $second);
    }
}
