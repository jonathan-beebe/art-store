<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Listing;
use App\Models\QuantityBreak;
use App\Models\Seller;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<QuantityBreak>
 */
class QuantityBreakFactory extends Factory
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
            'min_qty' => 10,
            'discount_bps' => 500,
        ];
    }
}
