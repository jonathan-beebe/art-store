<?php

declare(strict_types=1);

namespace App\Actions\Store;

use App\Models\StoreSection;
use Illuminate\Support\Facades\DB;

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
        return DB::transaction(function () use ($section, $heading, $body, $imageIds): StoreSection {
            $section->update(['heading' => $heading, 'body' => $body]);

            $section->sectionImages()->delete();

            foreach ($imageIds as $position => $imageId) {
                $section->sectionImages()->create(['store_image_id' => $imageId, 'position' => $position]);
            }

            return $section;
        });
    }
}
