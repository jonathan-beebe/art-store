<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Listing;
use App\Models\ListingImage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<ListingImage>
 */
class ListingImageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function definition(): array
    {
        return [
            'listing_id' => Listing::factory(),
            'path' => 'listings/'.fake()->unique()->uuid().'.jpg',
            'position' => 0,
        ];
    }
}
