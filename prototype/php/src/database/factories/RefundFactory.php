<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Auth\ActorType;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\Refund;
use App\Models\Seller;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<Refund>
 */
class RefundFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'fulfillment_id' => Fulfillment::factory(),
            'payment_id' => null,
            'amount_cents' => 10000,
            'reason' => 'The piece arrived damaged.',
            'issued_by_type' => ActorType::Seller->value,
            'issued_by_id' => Seller::factory(),
        ];
    }

    public function byAdmin(string $adminId): static
    {
        return $this->state(fn (array $attributes): array => [
            'issued_by_type' => ActorType::Admin->value,
            'issued_by_id' => $adminId,
        ]);
    }
}
