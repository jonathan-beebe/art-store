<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Store\StoreLinkKind;
use App\Models\StoreLink;
use App\Models\StoreProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<StoreLink>
 */
class StoreLinkFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function definition(): array
    {
        return [
            'store_profile_id' => StoreProfile::factory(),
            'kind' => StoreLinkKind::Website,
            'url' => 'https://example.com',
            'position' => 0,
        ];
    }

    public function instagram(): static
    {
        return $this->state(fn (): array => [
            'kind' => StoreLinkKind::Instagram,
            'url' => '@theburrowcraftworks',
            'position' => 1,
        ]);
    }
}
