<?php

declare(strict_types=1);

namespace App\Seller;

use App\Analytics\AnalyticsReport;
use App\Analytics\ListingEventCounts;
use App\Domain\Analytics\AnalyticsRange;
use App\Domain\Orders\FulfillmentStatus;
use App\Domain\Orders\OrderStatus;
use App\Domain\Seller\ListingTableRow;
use App\Models\Listing;
use App\Models\ListingAttribute;
use App\Models\OrderItem;
use App\Models\Seller;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use LogicException;

/**
 * `$seller`'s listings joined, by id in PHP, to the analytics store's
 * ranged event counts and the app database's all-time sales — the seller
 * listings table and grid's source. Rows come back in no particular
 * order; {@see \App\Domain\Seller\ListingTableSort} orders them.
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
            ->get();

        /** @var list<string> $listingIds */
        $listingIds = $listings->pluck('id')->all();

        $mediums = self::mediumsByListing($listingIds);
        $sales = self::salesByListing($seller, $listingIds);
        $counts = AnalyticsReport::countsForListingsSince($listingIds, $range->start);

        return array_values($listings->map(fn (Listing $listing): ListingTableRow => self::toRow(
            $listing,
            $mediums[$listing->id] ?? null,
            $sales[$listing->id] ?? ['sold' => 0, 'revenueCents' => 0],
            $counts[$listing->id] ?? new ListingEventCounts(views: 0, favorites: 0, cartAdds: 0),
        ))->all());
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
     * reads per listing, batched.
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
            ->get();

        return $attributes->mapWithKeys(fn (ListingAttribute $attribute): array => [$attribute->listing_id => $attribute->propertyValue->label])->all();
    }

    /**
     * How many units of each of `$listingIds` sold, and for how much: order
     * items on a paid order whose fulfillment is still live — declined and
     * refunded fulfillments settled their money back, so they no longer
     * count as a sale.
     *
     * @param  list<string>  $listingIds
     * @return array<string, array{sold: int, revenueCents: int}> listing id => totals
     */
    private static function salesByListing(Seller $seller, array $listingIds): array
    {
        if ($listingIds === []) {
            return [];
        }

        $paidStatuses = array_values(array_filter(
            OrderStatus::cases(),
            fn (OrderStatus $status): bool => $status->hasBeenPaid(),
        ));

        $items = OrderItem::query()
            ->where('seller_id', $seller->id)
            ->whereIn('listing_id', $listingIds)
            ->whereHas('order', fn (Builder $orders): Builder => $orders->whereIn('status', $paidStatuses))
            ->whereExists(function (QueryBuilder $query): void {
                $query->selectRaw('1')
                    ->from('fulfillments')
                    ->whereColumn('fulfillments.order_id', 'order_items.order_id')
                    ->whereColumn('fulfillments.seller_id', 'order_items.seller_id')
                    ->whereNotIn('fulfillments.status', [FulfillmentStatus::Declined->value, FulfillmentStatus::Refunded->value]);
            })
            ->get(['listing_id', 'quantity', 'unit_price_cents', 'price_breakdown_json']);

        $totals = [];

        foreach ($items as $item) {
            $totals[$item->listing_id]['sold'] = ($totals[$item->listing_id]['sold'] ?? 0) + $item->quantity;
            $totals[$item->listing_id]['revenueCents'] = ($totals[$item->listing_id]['revenueCents'] ?? 0) + $item->lineTotal()->cents;
        }

        return $totals;
    }
}
