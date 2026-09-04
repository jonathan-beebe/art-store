<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\StoreImage;
use App\Models\StoreSection;
use App\Models\StoreSectionImage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<StoreSectionImage>
 */
class StoreSectionImageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function definition(): array
    {
        return [
            'store_section_id' => StoreSection::factory()->gallery(),
            // The picture's store is the section's store. A row whose two
            // disagree is the state ownership must never read from.
            'store_image_id' => fn (array $attributes): mixed => $this->imageOf($attributes['store_section_id']),
            'position' => 0,
        ];
    }

    /**
     * A picture of the profile this section belongs to.
     */
    private function imageOf(mixed $sectionId): mixed
    {
        $section = is_string($sectionId) ? StoreSection::query()->find($sectionId) : null;

        return $section instanceof StoreSection
            ? StoreImage::factory()->create(['store_profile_id' => $section->store_profile_id])->id
            : StoreImage::factory();
    }
}
