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
            // The picture's seller is the profile's seller. A row whose two
            // disagree is the state ownership must never read from.
            'seller_id' => fn (array $attributes): mixed => $this->sellerOf($attributes['store_profile_id']),
            'path' => 'stores/'.fake()->unique()->uuid().'.jpg',
            'alt' => null,
        ];
    }

    /**
     * The seller of the profile this picture belongs to.
     *
     * @return string|Factory<Seller>
     */
    private function sellerOf(mixed $profileId): string|Factory
    {
        $profile = is_string($profileId) ? StoreProfile::query()->find($profileId) : null;

        return $profile instanceof StoreProfile ? $profile->seller_id : Seller::factory();
    }
}
