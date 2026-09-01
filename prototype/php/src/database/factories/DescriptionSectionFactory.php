<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Configurator\DescriptionSectionKind;
use App\Models\DescriptionSection;
use App\Models\Listing;
use App\Models\Seller;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<DescriptionSection>
 */
class DescriptionSectionFactory extends Factory
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
            'position' => 0,
            'kind' => DescriptionSectionKind::Text,
            'title' => fake()->sentence(3),
            'body_md' => fake()->paragraph(),
            'body_json' => null,
        ];
    }

    /**
     * @param  array<int|string, mixed>  $body
     */
    public function json(DescriptionSectionKind $kind, array $body): static
    {
        return $this->state(fn (array $attributes) => ['kind' => $kind, 'body_md' => null, 'body_json' => $body]);
    }
}
