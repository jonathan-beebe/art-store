<?php

declare(strict_types=1);

namespace App\Actions\Store;

use App\Logging\Story;
use App\Logging\StoryEvent;
use App\Models\StoreSection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * One section's own save: the text it carries and, for a gallery, which of
 * the store's pictures it places and in what order. Every placement is
 * written afresh, so the order the form sent is the order the page
 * renders.
 */
final readonly class SaveStoreSection
{
    /**
     * @param  list<string>  $imageIds  store image ids, in the order the gallery shows them
     */
    public function __invoke(StoreSection $section, ?string $heading, ?string $body, array $imageIds): StoreSection
    {
        $profile = $section->storeProfile ?? throw new RuntimeException('A store section belongs to a store.');

        return Story::for(StoryEvent::StoreSectionWrite)->tell('saving a store section', [
            'seller_id' => $profile->seller_id,
            'store_profile_id' => $profile->id,
            'section_id' => $section->id,
            'op' => 'save',
        ], function (Story $story) use ($profile, $section, $heading, $body, $imageIds): StoreSection {
            $saved = DB::transaction(function () use ($section, $heading, $body, $imageIds): StoreSection {
                $section->update(['heading' => $heading, 'body' => $body]);

                $section->sectionImages()->delete();

                foreach ($imageIds as $position => $imageId) {
                    $section->sectionImages()->create(['store_image_id' => $imageId, 'position' => $position]);
                }

                return $section;
            });

            $story->did('saved the store section', [
                'seller_id' => $profile->seller_id,
                'store_profile_id' => $profile->id,
                'section_id' => $saved->id,
                'kind' => $saved->kind->value,
                'op' => 'save',
            ]);

            return $saved;
        });
    }
}
