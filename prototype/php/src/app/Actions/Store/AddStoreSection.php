<?php

declare(strict_types=1);

namespace App\Actions\Store;

use App\Domain\Store\StoreSectionKind;
use App\Models\StoreProfile;
use App\Models\StoreSection;
use Illuminate\Support\Facades\DB;

/**
 * Appends one section to a store page, one place past whatever position
 * already runs highest.
 */
final readonly class AddStoreSection
{
    public function __invoke(StoreProfile $profile, StoreSectionKind $kind): StoreSection
    {
        return DB::transaction(function () use ($profile, $kind): StoreSection {
            // Holds the profile row for the rest of the transaction, so a
            // second add reads the position this one is about to write.
            StoreProfile::query()->whereKey($profile->id)->lockForUpdate()->sole();

            $highest = $profile->sections()->max('position');

            return $profile->sections()->create([
                'kind' => $kind,
                'position' => (is_numeric($highest) ? (int) $highest : -1) + 1,
            ]);
        });
    }
}
