<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Listings\ListingEventType;
use App\Models\Customer;
use App\Models\Listing;
use App\Models\ListingEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<ListingEvent>
 */
class ListingEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function definition(): array
    {
        return [
            'listing_id' => Listing::factory(),
            'customer_id' => Customer::factory(),
            'type' => ListingEventType::View,
            'occurred_at' => now(),
        ];
    }

    public function favorite(): static
    {
        return $this->state(fn (array $attributes): array => ['type' => ListingEventType::Favorite]);
    }

    public function unfavorite(): static
    {
        return $this->state(fn (array $attributes): array => ['type' => ListingEventType::Unfavorite]);
    }

    public function cartAdd(): static
    {
        return $this->state(fn (array $attributes): array => ['type' => ListingEventType::CartAdd]);
    }
}
