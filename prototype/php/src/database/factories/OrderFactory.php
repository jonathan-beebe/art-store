<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Orders\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * A bare order row for tests that need one to hang other rows off, not a
 * checked-out cart. A test that exercises the checkout flow itself walks it
 * through `Tests\CommerceTestCase::orderFor()` instead.
 *
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'email' => fake()->safeEmail(),
            'status' => OrderStatus::AwaitingPayment,
            'shipping_name' => fake()->name(),
            'shipping_line1' => fake()->streetAddress(),
            'shipping_line2' => null,
            'shipping_city' => fake()->city(),
            'shipping_region' => 'Greater London',
            'shipping_postal_code' => fake()->postcode(),
            'shipping_country' => 'GB',
            'subtotal_cents' => 10000,
            'total_cents' => 10000,
            'placed_at' => now(),
            'finalized_at' => null,
        ];
    }

    public function pendingVerification(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => OrderStatus::PendingVerification]);
    }

    public function awaitingPayment(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => OrderStatus::AwaitingPayment]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrderStatus::Paid,
            'finalized_at' => now(),
        ]);
    }
}
