<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Configurator\PricingMode;
use App\Models\Listing;
use App\Models\OptionAxis;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<OptionAxis>
 */
class OptionAxisFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function definition(): array
    {
        return [
            'listing_id' => Listing::factory(),
            'property_id' => null,
            'name' => ucfirst(fake()->unique()->word()),
            'position' => 0,
            'pricing_mode' => PricingMode::AddOn,
        ];
    }

    public function standalone(): static
    {
        return $this->state(fn (array $attributes) => ['pricing_mode' => PricingMode::Standalone]);
    }

    public function addOn(): static
    {
        return $this->state(fn (array $attributes) => ['pricing_mode' => PricingMode::AddOn]);
    }
}
