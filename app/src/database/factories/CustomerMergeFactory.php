<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerMerge;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<CustomerMerge>
 */
class CustomerMergeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function definition(): array
    {
        return [
            'anonymous_customer_id' => Customer::factory()->anonymous(),
            'customer_id' => Customer::factory(),
        ];
    }
}
