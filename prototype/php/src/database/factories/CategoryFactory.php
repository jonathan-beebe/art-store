<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Override;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'parent_id' => null,
            'name' => ucfirst($name),
            'path' => '/'.Str::slug($name).'/',
            'browsable' => true,
        ];
    }

    public function childOf(Category $parent, ?string $name = null): static
    {
        return $this->state(function (array $attributes) use ($parent, $name): array {
            $childName = $name ?? (is_string($attributes['name'] ?? null) ? $attributes['name'] : 'child');

            return [
                'parent_id' => $parent->id,
                'name' => $childName,
                'path' => $parent->path.Str::slug($childName).'/',
            ];
        });
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => ['browsable' => false]);
    }
}
