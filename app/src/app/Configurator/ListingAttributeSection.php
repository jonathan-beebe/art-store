<?php

declare(strict_types=1);

namespace App\Configurator;

use App\Models\CategoryProperty;
use App\Models\Listing;
use Illuminate\Support\Collection;

/**
 * The seller edit screen's attributes section, read-only: one control per
 * grant the listing's current category makes `usable_as_attribute`,
 * pre-selected from whatever the listing already holds. Shared by
 * {@see \App\Http\Controllers\Seller\ListingController} and
 * {@see \App\Http\Controllers\Seller\ListingAttributeController} so a
 * rate-limited resubmit re-renders the same screen the happy path built.
 */
final class ListingAttributeSection
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * @return Collection<int, CategoryProperty>
     */
    public static function grants(Listing $listing): Collection
    {
        return $listing->category_id === null
            ? new Collection
            : CategoryProperty::query()
                ->where('category_id', $listing->category_id)
                ->where('usable_as_attribute', true)
                ->with('property.values')
                ->orderBy('id')
                ->get();
    }

    /**
     * @return array<string, list<string>> property id => the property_value ids the listing already carries
     */
    public static function selections(Listing $listing): array
    {
        $selections = [];

        foreach ($listing->listingAttributes()->get() as $attribute) {
            $selections[$attribute->property_id][] = $attribute->property_value_id;
        }

        return $selections;
    }
}
