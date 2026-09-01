<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Modifier;
use App\Models\ModifierScope;
use App\Models\OptionValue;
use App\Models\Seller;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<ModifierScope>
 */
class ModifierScopeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function definition(): array
    {
        return [
            'modifier_id' => Modifier::factory(),
            'seller_id' => Seller::factory(),
            'option_value_id' => OptionValue::factory(),
        ];
    }
}
