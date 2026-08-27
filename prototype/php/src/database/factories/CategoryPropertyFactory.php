<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use App\Models\CategoryProperty;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<CategoryProperty>
 */
class CategoryPropertyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'property_id' => Property::factory(),
            'usable_as_attribute' => true,
            'usable_as_axis' => false,
            'required' => false,
            'multivalued' => false,
        ];
    }

    public function usableAsAxis(): static
    {
        return $this->state(fn (array $attributes) => ['usable_as_axis' => true]);
    }

    public function required(): static
    {
        return $this->state(fn (array $attributes) => ['required' => true]);
    }
}
