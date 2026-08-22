<?php

namespace App\Actions\Listings;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class StoreListingImage
{
    private const DIRECTORY = 'listings';

    /**
     * @return string the path on the public disk, for `listings.image_path`
     */
    public function __invoke(UploadedFile $image): string
    {
        return Storage::disk('public')->putFile(self::DIRECTORY, $image);
    }
}
