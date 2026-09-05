<?php

declare(strict_types=1);

namespace App\Support\Shop;

use App\Models\Listing;
use App\Models\ListingAttribute;
use App\Models\Property;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use LogicException;

/**
 * The browse row's data with photo covers: every for-sale medium from
 * {@see MediumOptions}, each carrying its for-sale listing count and a
 * cover image — the most-favorited for-sale listing in that medium
 * (newest breaks the tie), so the picker refreshes as the catalog moves.
 *
 * The count and cover for every medium are drawn from one pass over the
 * medium-tagged for-sale listings rather than a query per medium: every
 * `Medium` attribute row on a for-sale listing is fetched once, each
 * carrying its listing's favorites count, then sorted by the same
 * tie-break rule a single medium's cover would use (favorites desc, then
 * created_at, then id) and grouped by value — a medium's winner is the
 * first row of its group. The query count holds steady as the number of
 * mediums, or the size of the catalogue, grows.
 */
final class MediumBrowse
{
    /**
     * @return list<array{value: string, label: string, count: int, coverUrl: string}>
     */
    public static function forStorefront(): array
    {
        $options = MediumOptions::forStorefront();

        if ($options === []) {
            return [];
        }

        // $options is non-empty, so MediumOptions has already confirmed the
        // Medium property exists and at least one for-sale listing carries
        // it — the same guarantee that keeps every lookup below non-empty.
        $medium = Property::where('name', 'Medium')->firstOrFail();

        $forSaleListingIds = Listing::query()->forSale()->pluck('id');

        $attributes = ListingAttribute::query()
            ->where('property_id', $medium->id)
            ->whereIn('listing_id', $forSaleListingIds)
            ->with('propertyValue:id,label')
            ->get();

        $listingIds = $attributes->pluck('listing_id')->unique()->values()->all();

        /** @var Collection<string, Listing> $listingFactsById */
        $listingFactsById = Listing::query()
            ->select(['id', 'created_at'])
            ->whereIn('id', $listingIds)
            ->withCount('favorites')
            ->get()
            ->keyBy(fn (Listing $listing): string => $listing->id);

        $byValue = $attributes
            ->sort(function (ListingAttribute $a, ListingAttribute $b) use ($listingFactsById): int {
                $listingA = self::listingFrom($listingFactsById, $a->listing_id);
                $listingB = self::listingFrom($listingFactsById, $b->listing_id);

                return $listingB->favorites_count <=> $listingA->favorites_count
                    ?: $listingB->created_at <=> $listingA->created_at
                    ?: $b->listing_id <=> $a->listing_id;
            })
            ->groupBy(fn (ListingAttribute $attribute): string => mb_strtolower($attribute->propertyValue->label));

        /** @var array<string, string> $coverListingIdByValue */
        $coverListingIdByValue = $byValue
            ->map(fn (Collection $rows): string => self::firstOf($rows)->listing_id)
            ->all();

        /** @var array<string, int> $countByValue */
        $countByValue = $byValue
            ->map(fn (Collection $rows): int => $rows->pluck('listing_id')->unique()->count())
            ->all();

        $coversById = self::loadCovers(array_values(array_unique($coverListingIdByValue)));

        return array_map(function (array $option) use ($countByValue, $coverListingIdByValue, $coversById): array {
            $cover = self::listingFrom($coversById, $coverListingIdByValue[$option['value']]);

            return $option + [
                'count' => $countByValue[$option['value']],
                'coverUrl' => $cover->imageUrl(),
            ];
        }, $options);
    }

    /**
     * Every group `groupBy` produces holds at least one row, a guarantee the
     * generic `Collection::first()` signature does not carry.
     *
     * @param  Collection<int, ListingAttribute>  $rows
     */
    private static function firstOf(Collection $rows): ListingAttribute
    {
        return $rows->first() ?? throw new LogicException('A group groupBy() produces is never empty.');
    }

    /**
     * @param  Collection<string, Listing>  $listings
     */
    private static function listingFrom(Collection $listings, string $id): Listing
    {
        return $listings->get($id) ?? throw new LogicException("No listing loaded for id \"{$id}\".");
    }

    /**
     * Called only with the winning cover ids from a non-empty `$options` —
     * every medium in it is guaranteed at least one for-sale listing, so
     * unlike {@see CategoryBrowse::loadCovers()} there is no empty case
     * to guard.
     *
     * @param  list<string>  $listingIds
     * @return Collection<string, Listing>
     */
    private static function loadCovers(array $listingIds): Collection
    {
        /** @var Collection<string, Listing> $covers */
        $covers = Listing::query()
            ->whereIn('id', $listingIds)
            ->with(['images' => fn (Relation $images): Relation => $images->orderBy('position')])
            ->get()
            ->keyBy(fn (Listing $listing): string => $listing->id);

        return $covers;
    }
}
