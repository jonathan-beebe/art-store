<?php

declare(strict_types=1);

namespace App\Actions\Store;

use App\Domain\Store\StorePictureRole;
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
        DB::transaction(function () use ($image): void {
            $profile = $image->storeProfile;

            if ($profile instanceof StoreProfile) {
                $this->clearColumnsNaming($profile, $image);
            }

            $image->delete();
        });

        Storage::disk('public')->delete($image->path);
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
