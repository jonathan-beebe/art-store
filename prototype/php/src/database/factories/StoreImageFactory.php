<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Seller;
use App\Models\StoreImage;
use App\Models\StoreProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<StoreImage>
 */
class StoreImageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function definition(): array
    {
        return [
            'store_profile_id' => StoreProfile::factory(),
            'seller_id' => Seller::factory(),
            'path' => 'stores/'.fake()->unique()->uuid().'.jpg',
            'alt' => null,
        ];
    }
}
