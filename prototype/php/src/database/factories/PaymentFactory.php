<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Payments\DeclineReason;
use App\Domain\Payments\PaymentStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
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
            'status' => PaymentStatus::Approved,
            'amount_cents' => 10000,
            'card_last_four' => '4242',
            'decline_reason' => null,
            'processed_at' => now(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PaymentStatus::Approved,
            'card_last_four' => '4242',
            'decline_reason' => null,
        ]);
    }

    public function declined(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PaymentStatus::Declined,
            'card_last_four' => '0002',
            'decline_reason' => DeclineReason::GenericDecline,
        ]);
    }
}
