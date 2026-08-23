<?php

declare(strict_types=1);

namespace App\Actions\Listings;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final readonly class StoreListingImage
{
    private const DIRECTORY = 'listings';

    /**
     * @return string|null the path on the public disk, for `listings.image_path`;
     *                     null when the disk write failed
     */
    public function __invoke(UploadedFile $image): ?string
    {
        $path = Storage::disk('public')->putFile(self::DIRECTORY, $image);

        return $path === false ? null : $path;
    }
}
