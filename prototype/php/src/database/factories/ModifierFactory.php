<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Configurator\ModifierKind;
use App\Models\Listing;
use App\Models\Modifier;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<Modifier>
 */
class ModifierFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function definition(): array
    {
        return [
            'listing_id' => Listing::factory(),
            'kind' => ModifierKind::Text,
            'prompt' => fake()->sentence(4),
            'instructions' => null,
            'required' => false,
            'position' => 0,
            'add_on_price_cents' => 0,
            'char_limit' => null,
            'unit' => null,
            'min_value' => null,
            'max_value' => null,
            'rate_cents_per_unit' => null,
        ];
    }

    public function select(): static
    {
        return $this->state(fn (array $attributes) => ['kind' => ModifierKind::Select]);
    }

    public function measurement(string $unit, float $min, float $max, ?int $rateCentsPerUnit = null): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => ModifierKind::Measurement,
            'unit' => $unit,
            'min_value' => $min,
            'max_value' => $max,
            'rate_cents_per_unit' => $rateCentsPerUnit,
        ]);
    }

    public function required(): static
    {
        return $this->state(fn (array $attributes) => ['required' => true]);
    }
}
