<?php

declare(strict_types=1);

namespace App\Actions\Store;

use App\Domain\Store\StorePictureRole;
use App\Logging\Story;
use App\Logging\StoryEvent;
use App\Models\StoreImage;
use App\Models\StoreProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Puts an uploaded file on the public disk and adds it to a store's
 * pictures. A portrait or a cover also becomes the column the profile
 * points at; a gallery picture waits for a section to place it.
 *
 * The disk write happens before the transaction, so a transaction that
 * rolls back would leave the file behind with no row naming it. The catch
 * takes the file off the disk again, keeping the two in step. A failed disk
 * write leaves the store's pictures untouched and tells no story.
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

        return Story::for(StoryEvent::StoreImageWrite)->tell('adding a store picture', [
            'seller_id' => $profile->seller_id,
            'store_profile_id' => $profile->id,
            'op' => 'add',
        ], function (Story $story) use ($profile, $path, $role, $alt): StoreImage {
            try {
                $image = DB::transaction(fn (): StoreImage => $this->attach($profile, $path, $role, $alt));
            } catch (Throwable $e) {
                Storage::disk('public')->delete($path);

                throw $e;
            }

            $story->did('added the store picture', [
                'seller_id' => $profile->seller_id,
                'store_profile_id' => $profile->id,
                'image_id' => $image->id,
                'op' => 'add',
            ]);

            return $image;
        });
    }

    private function attach(StoreProfile $profile, string $path, StorePictureRole $role, ?string $alt): StoreImage
    {
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
    }
}
