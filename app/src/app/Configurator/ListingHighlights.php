<?php

declare(strict_types=1);

namespace App\Configurator;

use App\Models\Listing;

/**
 * `/art/{slug}`'s Highlights panel: a listing's fixed, category-gated facts
 * (Metal: Gold) grouped by property name, in the order the
 * seller set them. A listing with no `listing_attributes` rows resolves to
 * an empty list, so the panel renders nothing. Medium is left out — the
 * page's own Medium line already carries it, so repeating it here would
 * only echo the same fact twice.
 */
final class ListingHighlights
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * @return list<array{name: string, values: list<string>}>
     */
    public static function forStorefront(Listing $listing): array
    {
        $byPropertyId = [];
        $order = [];

        $listing->loadMissing(['listingAttributes.property', 'listingAttributes.propertyValue']);

        foreach ($listing->listingAttributes as $attribute) {
            if ($attribute->property->name === 'Medium') {
                continue;
            }

            $propertyId = $attribute->property_id;

            if (! array_key_exists($propertyId, $byPropertyId)) {
                $byPropertyId[$propertyId] = ['name' => $attribute->property->name, 'values' => []];
                $order[] = $propertyId;
            }

            $byPropertyId[$propertyId]['values'][] = $attribute->propertyValue->label;
        }

        return array_map(fn (string $propertyId): array => $byPropertyId[$propertyId], $order);
    }
}
