<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Listing;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Seller;
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
            'listing_id' => Listing::factory(),
            'seller_id' => Seller::factory(),
            'title' => fake()->sentence(3),
            'unit_price_cents' => fake()->numberBetween(2500, 250000),
            'quantity' => 1,
        ];
    }
}
