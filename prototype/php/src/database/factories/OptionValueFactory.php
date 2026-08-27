<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OptionAxis;
use App\Models\OptionValue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<OptionValue>
 */
class OptionValueFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function definition(): array
    {
        return [
            'axis_id' => OptionAxis::factory(),
            'property_value_id' => null,
            'label' => ucfirst(fake()->unique()->word()),
            'surcharge_cents' => 0,
            'is_default' => false,
            'position' => 0,
        ];
    }

    public function surcharging(int $cents): static
    {
        return $this->state(fn (array $attributes) => ['surcharge_cents' => $cents]);
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => ['is_default' => true]);
    }
}
