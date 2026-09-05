<?php

declare(strict_types=1);

namespace App\Actions\Store;

use App\Logging\Story;
use App\Logging\StoryEvent;
use App\Models\StoreSection;
use RuntimeException;

/**
 * Takes one section off a store page. The gallery placements go with it;
 * the pictures they named stay in the store's pictures.
 */
final readonly class RemoveStoreSection
{
    public function __invoke(StoreSection $section): void
    {
        $profile = $section->storeProfile ?? throw new RuntimeException('A store section belongs to a store.');
        $sectionId = $section->id;
        $kind = $section->kind->value;

        Story::for(StoryEvent::StoreSectionWrite)->tell('removing a store section', [
            'seller_id' => $profile->seller_id,
            'store_profile_id' => $profile->id,
            'section_id' => $sectionId,
            'op' => 'remove',
        ], function (Story $story) use ($section, $profile, $sectionId, $kind): void {
            $section->delete();

            $story->did('removed the store section', [
                'seller_id' => $profile->seller_id,
                'store_profile_id' => $profile->id,
                'section_id' => $sectionId,
                'kind' => $kind,
                'op' => 'remove',
            ]);
        });
    }
}
