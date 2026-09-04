<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Listing;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Seller;
use App\Models\Variant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'customer_id' => Customer::factory(),
            'listing_id' => Listing::factory(),
            'seller_id' => Seller::factory(),
            'title' => fake()->sentence(3),
            'unit_price_cents' => fake()->numberBetween(2500, 250000),
            'quantity' => 1,
        ];
    }

    /**
     * A configured line's frozen shape: a variant it claimed, one axis pair,
     * and an itemized breakdown — the fixture every test of the configured
     * rendering/pricing path builds on.
     */
    public function configured(): static
    {
        return $this->state(fn (array $attributes): array => [
            'variant_id' => Variant::factory(),
            'configuration_json' => [
                ['axisId' => 'axs_00000000000000000000000001', 'axisName' => 'Metal', 'optionValueId' => 'ovl_00000000000000000000000001', 'optionValueLabel' => 'Rose Gold'],
            ],
            'answers_json' => null,
            'price_breakdown_json' => [
                ['label' => 'Base price', 'cents' => 12000],
                ['label' => 'Rose Gold', 'cents' => 800],
            ],
        ]);
    }
}
