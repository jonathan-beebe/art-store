<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Configurator\CartLineFingerprint;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\Listing;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<CartItem>
 */
class CartItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function definition(): array
    {
        return [
            'cart_id' => Cart::factory(),
            'customer_id' => Customer::factory(),
            'listing_id' => Listing::factory(),
            'variant_id' => null,
            'unit_id' => null,
            'quantity' => fake()->numberBetween(1, 3),
            'configuration_json' => null,
            'answers_json' => null,
            'fingerprint' => CartLineFingerprint::of(null, null, [])->value,
        ];
    }
}
