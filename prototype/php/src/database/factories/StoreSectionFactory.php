<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Store\StoreSectionKind;
use App\Models\StoreProfile;
use App\Models\StoreSection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<StoreSection>
 */
class StoreSectionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function definition(): array
    {
        return [
            'store_profile_id' => StoreProfile::factory(),
            'kind' => StoreSectionKind::Story,
            'position' => 0,
            'heading' => 'My story',
            'body' => 'Everything here is made in the kitchen, the shed, or the orchard.',
        ];
    }

    public function gallery(): static
    {
        return $this->state(fn (): array => [
            'kind' => StoreSectionKind::Gallery,
            'heading' => 'The studio',
            'body' => null,
        ]);
    }
}
