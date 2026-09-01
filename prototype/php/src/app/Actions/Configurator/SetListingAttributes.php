<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Logging\StoryEvent;
use App\Models\CategoryProperty;
use App\Models\Listing;
use App\Models\ListingAttribute;
use App\Models\PropertyValue;
use App\Support\Story;
use Illuminate\Support\Collection;

/**
 * The attributes section on the listing edit screen: replaces a listing's
 * fixed, category-gated facts with the values just checked, one property at a
 * time — the same "set, don't add to" shape {@see SetModifierScope} gives a
 * modifier's scope. Only a property the listing's current category actually
 * grants as `usable_as_attribute` is honored; a category change prunes the
 * rest before this ever runs (see {@see \App\Actions\Listings\UpdateListing}),
 * so a stale form submitted against an old category touches nothing outside
 * what the listing grants today.
 */
final readonly class SetListingAttributes
{
    /**
     * @param  array<string, list<string>>  $selections  property id => the property_value ids checked for it
     * @return list<ListingAttribute>
     */
    public function __invoke(Listing $listing, array $selections): array
    {
        return Story::for(StoryEvent::ListingUpdate)->tell('setting a listing’s attributes', [
            'listing_id' => $listing->id,
        ], function (Story $story) use ($listing, $selections): array {
            /** @var Collection<int, CategoryProperty> $grants */
            $grants = $listing->category_id === null
                ? new Collection
                : CategoryProperty::query()
                    ->where('category_id', $listing->category_id)
                    ->where('usable_as_attribute', true)
                    ->get();

            $attributes = [];

            foreach ($grants as $grant) {
                foreach ($this->set($listing, $grant, $selections[$grant->property_id] ?? []) as $attribute) {
                    $attributes[] = $attribute;
                }
            }

            $story->did('set the listing’s attributes', [
                'listing_id' => $listing->id,
                'property_ids' => array_values($grants->pluck('property_id')->all()),
            ]);

            return $attributes;
        });
    }

    /**
     * @param  list<string>  $requestedValueIds
     * @return list<ListingAttribute>
     */
    private function set(Listing $listing, CategoryProperty $grant, array $requestedValueIds): array
    {
        $requestedValueIds = array_values(array_unique($requestedValueIds));

        if (! $grant->multivalued) {
            $requestedValueIds = array_slice($requestedValueIds, 0, 1);
        }

        /** @var list<string> $validValueIds */
        $validValueIds = $requestedValueIds === [] ? [] : array_values(PropertyValue::query()
            ->where('property_id', $grant->property_id)
            ->whereIn('id', $requestedValueIds)
            ->pluck('id')
            ->all());

        $listing->listingAttributes()
            ->where('property_id', $grant->property_id)
            ->whereNotIn('property_value_id', $validValueIds)
            ->delete();

        return array_map(
            fn (string $valueId): ListingAttribute => $listing->listingAttributes()->firstOrCreate([
                'property_id' => $grant->property_id,
                'property_value_id' => $valueId,
            ], [
                'seller_id' => $listing->seller_id,
            ]),
            $validValueIds,
        );
    }
}
