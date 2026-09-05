<?php

declare(strict_types=1);

namespace App\Support\Shop;

use App\Models\Listing;
use App\Models\ListingAttribute;
use App\Models\Property;
use App\Models\PropertyValue;

/**
 * The storefront's browse row: every Medium value at least one for-sale
 * listing carries, ordered by label — the URL value is the label
 * lowercased, matching {@see Listing::ofMediumAttribute()}. Shared by the
 * storefront and the design-system page's category-tile specimen.
 */
final class MediumOptions
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function forStorefront(): array
    {
        $medium = Property::where('name', 'Medium')->first();

        if ($medium === null) {
            return [];
        }

        $forSaleListingIds = Listing::query()->forSale()->pluck('id');

        $attributedValueIds = ListingAttribute::query()
            ->where('property_id', $medium->id)
            ->whereIn('listing_id', $forSaleListingIds)
            ->distinct()
            ->pluck('property_value_id');

        /** @var list<string> $labels */
        $labels = array_values(PropertyValue::query()
            ->where('property_id', $medium->id)
            ->whereIn('id', $attributedValueIds)
            ->orderBy('label')
            ->pluck('label')
            ->all());

        return array_map(fn (string $label): array => ['value' => mb_strtolower($label), 'label' => $label], $labels);
    }
}
