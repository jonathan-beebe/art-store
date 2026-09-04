<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Seller;
use App\Models\StoreProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<StoreProfile>
 */
class StoreProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'seller_id' => Seller::factory(),
            'slug' => 'store-'.fake()->unique()->numerify('######'),
            'name' => $name,
            'tagline' => 'Made by hand',
            'location' => 'Ottery St Catchpole, Devon',
            'published_at' => now(),
        ];
    }

    public function hidden(): static
    {
        return $this->state(fn (): array => ['published_at' => null]);
    }
}
