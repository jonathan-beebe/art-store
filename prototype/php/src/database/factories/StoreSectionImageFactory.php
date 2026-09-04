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
            'store_image_id' => StoreImage::factory(),
            'position' => 0,
        ];
    }
}
