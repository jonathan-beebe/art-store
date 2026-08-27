<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Configurator\UnitState;
use App\Models\Unit;
use App\Models\Variant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function definition(): array
    {
        return [
            'variant_id' => Variant::factory(),
            'label' => '#'.fake()->unique()->numberBetween(1, 999999),
            'state' => UnitState::Available,
            'condition_note' => null,
            'specs_json' => null,
            'price_override_cents' => null,
        ];
    }

    public function reserved(): static
    {
        return $this->state(fn (array $attributes) => ['state' => UnitState::Reserved]);
    }

    public function sold(): static
    {
        return $this->state(fn (array $attributes) => ['state' => UnitState::Sold]);
    }
}
