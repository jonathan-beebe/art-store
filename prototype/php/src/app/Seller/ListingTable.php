<?php

declare(strict_types=1);

namespace App\Seller;

use App\Analytics\AnalyticsReport;
use App\Analytics\ListingEventCounts;
use App\Domain\Analytics\AnalyticsRange;
use App\Domain\Seller\ListingTableRow;
use App\Models\Fulfillment;
use App\Models\Listing;
use App\Models\ListingAttribute;
use App\Models\OrderItem;
use App\Models\Seller;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use LogicException;

/**
 * A seller's listings joined, by id in PHP, to the analytics store's
 * ranged event counts and the app database's all-time sales — the seller
 * listings table and grid's source, and the one detail component's source
 * for the same figures on a single listing. Rows come back in no
 * particular order; {@see \App\Domain\Seller\RowSort} orders a
 * list of them.
 */
final class ListingTable
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * @return list<ListingTableRow>
     */
    public static function forSeller(Seller $seller, AnalyticsRange $range): array
    {
        $listings = Listing::query()
            ->ofSeller($seller->id)
            ->with(['activeRemoval', 'images' => fn (Relation $images): Relation => $images->orderBy('position')])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return array_values(self::rowsFor($listings, $seller->id, $range)->all());
    }

    /**
     * The same row a table or grid renders, for one listing already
     * carrying its `activeRemoval` — the detail component's source, so a
     * listing's sold, revenue, and ranged counts never disagree with its
     * own row in the table.
     */
    public static function forListing(Listing $listing, AnalyticsRange $range): ListingTableRow
    {
        return self::rowsFor(collect([$listing]), $listing->seller_id, $range)->sole();
    }

    /**
     * @param  Collection<int, Listing>  $listings
     * @return Collection<int, ListingTableRow>
     */
    private static function rowsFor(Collection $listings, string $sellerId, AnalyticsRange $range): Collection
    {
        /** @var list<string> $listingIds */
        $listingIds = $listings->pluck('id')->all();

        $mediums = self::mediumsByListing($listingIds);
        $sales = self::salesByListing($sellerId, $listingIds);
        $counts = AnalyticsReport::countsForListingsSince($listingIds, $range->start);

        return $listings->map(fn (Listing $listing): ListingTableRow => self::toRow(
            $listing,
            $mediums[$listing->id] ?? null,
            $sales[$listing->id] ?? ['sold' => 0, 'revenueCents' => 0],
            $counts[$listing->id] ?? new ListingEventCounts(views: 0, favorites: 0, cartAdds: 0),
        ))->values();
    }

    /**
     * @param  array{sold: int, revenueCents: int}  $sale
     */
    private static function toRow(Listing $listing, ?string $medium, array $sale, ListingEventCounts $counts): ListingTableRow
    {
        $hasActiveRemoval = (bool) $listing->activeRemoval;

        return new ListingTableRow(
            id: $listing->id,
            title: $listing->title,
            imageUrl: $listing->imageUrl(),
            medium: $medium,
            dimensions: $listing->dimensions,
            statusLabel: $listing->status->sellerBadgeLabel($hasActiveRemoval),
            statusTint: $listing->status->sellerBadgeTint($hasActiveRemoval),
            priceCents: $listing->price_cents,
            quantity: $listing->quantity,
            views: $counts->views,
            favorites: $counts->favorites,
            cartAdds: $counts->cartAdds,
            sold: $sale['sold'],
            revenueCents: $sale['revenueCents'],
            updatedAt: DateTimeImmutable::createFromInterface($listing->updated_at ?? throw new LogicException('A persisted listing always carries updated_at.')),
        );
    }

    /**
     * The Medium attribute label for each of `$listingIds` that carries
     * one, one query — the same fact {@see Listing::mediumAttributeLabel()}
     * reads per listing, batched. Ordered by id, keeping the first row per
     * listing — the same "first" `mediumAttributeLabel()` reads.
     *
     * @param  list<string>  $listingIds
     * @return array<string, string> listing id => label
     */
    private static function mediumsByListing(array $listingIds): array
    {
        if ($listingIds === []) {
            return [];
        }

        /** @var Collection<int, ListingAttribute> $attributes */
        $attributes = ListingAttribute::query()
            ->whereIn('listing_id', $listingIds)
            ->whereHas('property', fn (Builder $properties): Builder => $properties->where('name', 'Medium'))
            ->with('propertyValue')
            ->orderBy('id')
            ->get();

        $mediums = [];

        foreach ($attributes as $attribute) {
            $mediums[$attribute->listing_id] ??= $attribute->propertyValue->label;
        }

        return $mediums;
    }

    /**
     * How many units of each of `$listingIds` sold, and for how much: order
     * items riding on a fulfillment {@see Fulfillment::counted()} counts —
     * a declined or refunded fulfillment settled its money back, so it no
     * longer counts as a sale.
     *
     * @param  list<string>  $listingIds
     * @return array<string, array{sold: int, revenueCents: int}> listing id => totals
     */
    private static function salesByListing(string $sellerId, array $listingIds): array
    {
        if ($listingIds === []) {
            return [];
        }

        $items = OrderItem::query()
            ->where('seller_id', $sellerId)
            ->whereIn('listing_id', $listingIds)
            ->whereExists(
                Fulfillment::query()
                    ->counted()
                    ->whereColumn('fulfillments.order_id', 'order_items.order_id')
                    ->whereColumn('fulfillments.seller_id', 'order_items.seller_id'),
            )
            ->get(['listing_id', 'quantity', 'unit_price_cents', 'price_breakdown_json']);

        $totals = [];

        foreach ($items as $item) {
            $totals[$item->listing_id]['sold'] = ($totals[$item->listing_id]['sold'] ?? 0) + $item->quantity;
            $totals[$item->listing_id]['revenueCents'] = ($totals[$item->listing_id]['revenueCents'] ?? 0) + $item->lineTotal()->cents;
        }

        return $totals;
    }
}
