<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerBlock;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<CustomerBlock>
 */
class CustomerBlockFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'reason' => fake()->sentence(),
            'lifted_at' => null,
        ];
    }

    public function lifted(): static
    {
        return $this->state(fn (array $attributes) => [
            'lifted_at' => now(),
        ]);
    }
}
