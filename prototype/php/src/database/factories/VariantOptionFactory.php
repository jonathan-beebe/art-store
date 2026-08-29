<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OptionAxis;
use App\Models\OptionValue;
use App\Models\Variant;
use App\Models\VariantOption;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<VariantOption>
 */
class VariantOptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function definition(): array
    {
        return [
            'variant_id' => Variant::factory(),
            'axis_id' => OptionAxis::factory(),
            'option_value_id' => OptionValue::factory(),
        ];
    }
}
