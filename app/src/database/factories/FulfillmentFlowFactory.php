<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FulfillmentFlow;
use App\Models\Seller;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<FulfillmentFlow>
 */
class FulfillmentFlowFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function definition(): array
    {
        return [
            'seller_id' => Seller::factory(),
            'name' => 'How I ship',
            'is_default' => false,
        ];
    }

    public function isDefault(): static
    {
        return $this->state(fn (array $attributes): array => ['is_default' => true]);
    }
}
