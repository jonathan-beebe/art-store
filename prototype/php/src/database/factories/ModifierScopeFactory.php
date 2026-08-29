<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Modifier;
use App\Models\ModifierScope;
use App\Models\OptionValue;
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
            'option_value_id' => OptionValue::factory(),
        ];
    }
}
