<?php

declare(strict_types=1);

namespace App\Actions\Store;

use App\Domain\Store\StorePictureRole;
use App\Models\StoreImage;
use App\Models\StoreProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Puts an uploaded file on the public disk and adds it to a store's
 * pictures. A portrait or a cover also becomes the column the profile
 * points at; a gallery picture waits for a section to place it.
 */
final readonly class AddStoreImage
{
    private const string DIRECTORY = 'stores';

    /**
     * @return StoreImage|null null when the disk write failed, leaving the
     *                         store's pictures untouched
     */
    public function __invoke(StoreProfile $profile, UploadedFile $file, StorePictureRole $role, ?string $alt = null): ?StoreImage
    {
        $path = Storage::disk('public')->putFile(self::DIRECTORY, $file);

        if ($path === false) {
            return null;
        }

        return DB::transaction(function () use ($profile, $path, $role, $alt): StoreImage {
            $image = $profile->images()->create([
                'seller_id' => $profile->seller_id,
                'path' => $path,
                'alt' => $alt,
            ]);

            $column = $role->profileColumn();

            if ($column !== null) {
                $profile->update([$column => $image->id]);
            }

            return $image;
        });
    }
}
