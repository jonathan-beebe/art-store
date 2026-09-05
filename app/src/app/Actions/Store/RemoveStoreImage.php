<?php

declare(strict_types=1);

namespace App\Actions\Store;

use App\Domain\Store\StorePictureRole;
use App\Logging\Story;
use App\Logging\StoryEvent;
use App\Models\StoreImage;
use App\Models\StoreProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Takes one picture out of a store. A profile column pointing at it is
 * cleared first, so the page never names a row that is gone; the gallery
 * placements go with the row. The file follows the row off disk: every
 * store picture, seeded or uploaded, is a file the store alone owns.
 */
final readonly class RemoveStoreImage
{
    public function __invoke(StoreImage $image): void
    {
        $imageId = $image->id;
        $sellerId = $image->seller_id;
        $storeProfileId = $image->store_profile_id;

        Story::for(StoryEvent::StoreImageWrite)->tell('removing a store picture', [
            'seller_id' => $sellerId,
            'store_profile_id' => $storeProfileId,
            'image_id' => $imageId,
            'op' => 'remove',
        ], function (Story $story) use ($image, $imageId, $sellerId, $storeProfileId): void {
            DB::transaction(function () use ($image): void {
                $profile = $image->storeProfile;

                if ($profile instanceof StoreProfile) {
                    $this->clearColumnsNaming($profile, $image);
                }

                $image->delete();
            });

            Storage::disk('public')->delete($image->path);

            $story->did('removed the store picture', [
                'seller_id' => $sellerId,
                'store_profile_id' => $storeProfileId,
                'image_id' => $imageId,
                'op' => 'remove',
            ]);
        });
    }

    private function clearColumnsNaming(StoreProfile $profile, StoreImage $image): void
    {
        $cleared = [];

        foreach (StorePictureRole::cases() as $role) {
            $column = $role->profileColumn();

            if ($column !== null && $profile->getAttribute($column) === $image->id) {
                $cleared[$column] = null;
            }
        }

        if ($cleared !== []) {
            $profile->update($cleared);
        }
    }
}
