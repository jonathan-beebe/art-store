<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Payout;
use App\Models\Seller;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<Payout>
 */
class PayoutFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function definition(): array
    {
        $start = now()->subWeek()->startOfWeek();

        return [
            'seller_id' => Seller::factory(),
            'period_start' => $start,
            'period_end' => $start->copy()->endOfWeek(),
            'amount_cents' => 9000,
            'paid_at' => now(),
        ];
    }
}
