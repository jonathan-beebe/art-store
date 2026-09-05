<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Orders\FulfillmentStatus;
use App\Models\Customer;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\Seller;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<Fulfillment>
 */
class FulfillmentFactory extends Factory
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
            'seller_id' => Seller::factory(),
            'status' => FulfillmentStatus::AwaitingShipment,
            'carrier' => null,
            'tracking_number' => null,
            'shipped_at' => null,
            'delivered_at' => null,
            'subtotal_cents' => 10000,
            'fee_cents' => 1000,
            'net_cents' => 9000,
        ];
    }

    public function awaitingShipment(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => FulfillmentStatus::AwaitingShipment,
            'carrier' => null,
            'tracking_number' => null,
            'shipped_at' => null,
            'delivered_at' => null,
        ]);
    }

    public function shipped(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => FulfillmentStatus::Shipped,
            'carrier' => 'Royal Mail',
            'tracking_number' => 'RM'.fake()->unique()->numberBetween(100, 999),
            'shipped_at' => now(),
            'delivered_at' => null,
        ]);
    }

    public function delivered(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => FulfillmentStatus::Delivered,
            'carrier' => $attributes['carrier'] ?? 'Royal Mail',
            'tracking_number' => $attributes['tracking_number'] ?? 'RM'.fake()->unique()->numberBetween(100, 999),
            'shipped_at' => $attributes['shipped_at'] ?? now()->subDay(),
            'delivered_at' => now(),
        ]);
    }
}
