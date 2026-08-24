<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Listings\ListingRemovalKind;
use App\Models\Listing;
use App\Models\ListingRemoval;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<ListingRemoval>
 */
class ListingRemovalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function definition(): array
    {
        return [
            'listing_id' => Listing::factory(),
            'kind' => ListingRemovalKind::Temporary,
            'reason' => fake()->sentence(),
            'lifted_at' => null,
        ];
    }

    public function permanent(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => ListingRemovalKind::Permanent,
        ]);
    }

    public function lifted(): static
    {
        return $this->state(fn (array $attributes) => [
            'lifted_at' => now(),
        ]);
    }
}
