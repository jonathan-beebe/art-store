<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Listings\ListingStatus;
use App\Models\Listing;
use App\Models\Seller;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Listing>
 */
class ListingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'seller_id' => Seller::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 999999),
            'description' => fake()->paragraph(),
            'price_cents' => fake()->numberBetween(2500, 250000),
            'quantity' => 1,
            'status' => ListingStatus::ForSale,
            'medium' => fake()->randomElement(['oil', 'print', 'ceramic', 'watercolour']),
            'dimensions' => '12 x 16 in',
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ListingStatus::Draft]);
    }

    public function soldOut(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ListingStatus::Sold,
            'quantity' => 0,
        ]);
    }
}
