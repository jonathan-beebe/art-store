<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Override;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'name' => fake()->name(),
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    public function anonymous(): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => null,
            'name' => null,
            'email_verified_at' => null,
        ]);
    }
}
