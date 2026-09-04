<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Configurator\PropertyDataType;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->word()),
            'data_type' => PropertyDataType::Enum,
        ];
    }

    public function text(): static
    {
        return $this->state(fn (array $attributes) => ['data_type' => PropertyDataType::Text]);
    }

    public function number(): static
    {
        return $this->state(fn (array $attributes) => ['data_type' => PropertyDataType::Number]);
    }
}
