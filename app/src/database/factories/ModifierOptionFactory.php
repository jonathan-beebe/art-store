<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Modifier;
use App\Models\ModifierOption;
use App\Models\Seller;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<ModifierOption>
 */
class ModifierOptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function definition(): array
    {
        return [
            'modifier_id' => Modifier::factory(),
            'seller_id' => Seller::factory(),
            'label' => ucfirst(fake()->unique()->word()),
            'add_on_price_cents' => 0,
            'position' => 0,
        ];
    }

    public function pricedAt(int $cents): static
    {
        return $this->state(fn (array $attributes) => ['add_on_price_cents' => $cents]);
    }
}
