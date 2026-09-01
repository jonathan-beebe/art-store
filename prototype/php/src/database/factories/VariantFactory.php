<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Listing;
use App\Models\Seller;
use App\Models\Variant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<Variant>
 */
class VariantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function definition(): array
    {
        return [
            'listing_id' => Listing::factory(),
            'seller_id' => Seller::factory(),
            'combo_key' => '',
            'sku' => null,
            'price_override_cents' => null,
            'quantity' => 1,
            'is_serialized' => false,
            'enabled' => true,
        ];
    }

    public function withSku(string $sku): static
    {
        return $this->state(fn (array $attributes) => ['sku' => $sku]);
    }

    public function serialized(): static
    {
        return $this->state(fn (array $attributes) => ['is_serialized' => true, 'quantity' => null]);
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => ['enabled' => false]);
    }

    public function overriddenAt(int $cents): static
    {
        return $this->state(fn (array $attributes) => ['price_override_cents' => $cents]);
    }
}
