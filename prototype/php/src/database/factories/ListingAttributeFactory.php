<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Listing;
use App\Models\ListingAttribute;
use App\Models\Property;
use App\Models\PropertyValue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<ListingAttribute>
 */
class ListingAttributeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function definition(): array
    {
        return [
            'listing_id' => Listing::factory(),
            'property_id' => Property::factory(),
            'property_value_id' => PropertyValue::factory(),
        ];
    }
}
