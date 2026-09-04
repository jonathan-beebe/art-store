<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Property;
use App\Models\PropertyValue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<PropertyValue>
 */
class PropertyValueFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'label' => ucfirst(fake()->unique()->word()),
            'position' => 0,
        ];
    }
}
