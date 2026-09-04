<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Funnel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Override;

/**
 * @extends Factory<Funnel>
 */
class FunnelFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function definition(): array
    {
        /** @var string $name */
        $name = fake()->unique()->words(2, true);

        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'steps' => ['listing.view', 'listing.cart_add'],
            'position' => 1,
        ];
    }
}
