<?php

declare(strict_types=1);

namespace App\Actions\Store;

use App\Domain\Store\StoreSectionKind;
use App\Models\StoreProfile;
use App\Models\StoreSection;

/**
 * Appends one section to a store page, one place past whatever position
 * already runs highest.
 */
final readonly class AddStoreSection
{
    public function __invoke(StoreProfile $profile, StoreSectionKind $kind): StoreSection
    {
        $highest = $profile->sections()->max('position');

        return $profile->sections()->create([
            'kind' => $kind,
            'position' => (is_numeric($highest) ? (int) $highest : -1) + 1,
        ]);
    }
}
