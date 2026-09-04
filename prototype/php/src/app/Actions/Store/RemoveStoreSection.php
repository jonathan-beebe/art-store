<?php

declare(strict_types=1);

namespace App\Actions\Store;

use App\Models\StoreSection;

/**
 * Takes one section off a store page. The gallery placements go with it;
 * the pictures they named stay in the store's pictures.
 */
final readonly class RemoveStoreSection
{
    public function __invoke(StoreSection $section): void
    {
        $section->delete();
    }
}
