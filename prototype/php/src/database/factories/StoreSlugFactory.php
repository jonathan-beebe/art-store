<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\StoreProfile;
use App\Models\StoreSlug;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<StoreSlug>
 */
class StoreSlugFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function definition(): array
    {
        return [
            'store_profile_id' => StoreProfile::factory(),
            'slug' => 'store-'.fake()->unique()->numerify('######'),
            'retired_at' => null,
        ];
    }

    public function retired(): static
    {
        return $this->state(fn (): array => ['retired_at' => now()]);
    }
}
