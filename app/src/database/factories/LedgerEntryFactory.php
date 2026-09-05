<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Escrow\LedgerEntryType;
use App\Models\Fulfillment;
use App\Models\LedgerEntry;
use App\Models\Payout;
use App\Models\Seller;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<LedgerEntry>
 */
class LedgerEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function definition(): array
    {
        return [
            'seller_id' => Seller::factory(),
            'fulfillment_id' => Fulfillment::factory(),
            'payout_id' => null,
            'type' => LedgerEntryType::Held,
            'amount_cents' => 9000,
            'occurred_at' => now(),
        ];
    }

    public function held(): static
    {
        return $this->state(fn (array $attributes): array => ['type' => LedgerEntryType::Held]);
    }

    public function released(): static
    {
        return $this->state(fn (array $attributes): array => ['type' => LedgerEntryType::Released]);
    }

    public function paidOut(): static
    {
        return $this->state(function (array $attributes): array {
            $amount = is_int($attributes['amount_cents'] ?? null) ? $attributes['amount_cents'] : 9000;

            return [
                'type' => LedgerEntryType::PaidOut,
                'amount_cents' => -abs($amount),
                'fulfillment_id' => null,
                'payout_id' => Payout::factory(),
            ];
        });
    }
}
