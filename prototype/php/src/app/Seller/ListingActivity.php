<?php

declare(strict_types=1);

namespace App\Seller;

use App\Analytics\AnalyticsReport;
use App\Analytics\ListingEventCounts;
use App\Domain\Analytics\AnalyticsRange;
use App\Domain\Analytics\BarStrip;
use App\Domain\Orders\FulfillmentStatus;
use App\Domain\Seller\ActivityTotal;
use App\Domain\Seller\ListingSort;
use App\Domain\Seller\ListingSortColumn;
use App\Domain\Seller\ListingTableRow;
use App\Domain\Seller\ListingTableSort;
use App\Domain\Seller\SortDirection;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Seller;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * What buyers did on a seller's listings inside the range: four totals
 * with their change against the range before it, and the five listings
 * drawing the most views, each with a daily strip.
 *
 * The rows are {@see ListingTable}'s own, so a listing's figures on the
 * dashboard and in the listings table are the same figures. Views,
 * favorites, and cart adds come from the analytics store; sold is units
 * off paid orders whose parcel still stands, the sale
 * {@see ListingTable} counts, narrowed to the range.
 */
final readonly class ListingActivity
{
    /** How many listings the table shows. */
    private const int TOP_LISTINGS = 5;

    /** The longest strip a table cell reads at a glance. */
    private const int MAX_STRIP_DAYS = 30;

    private const int STRIP_HEIGHT_PX = 26;

    /**
     * @param  list<ActivityTotal>  $totals
     * @param  list<OverviewListingRow>  $rows
     */
    private function __construct(
        public array $totals,
        public array $rows,
        public int $stripDays,
    ) {}

    public static function for(Seller $seller, AnalyticsRange $range): self
    {
        $previous = $range->previous();
        $listings = ListingTable::forSeller($seller, $range);

        /** @var list<string> $listingIds */
        $listingIds = array_map(fn (ListingTableRow $row): string => $row->id, $listings);

        $earlier = AnalyticsReport::countsForListingsBetween($listingIds, $previous->start, $previous->end);
        $sold = self::unitsSoldByListingAndDay($seller, $previous->start, $range->end);

        $top = array_slice(
            ListingTableSort::apply(ListingSort::of(ListingSortColumn::Views, SortDirection::Desc), $listings),
            0,
            self::TOP_LISTINGS,
        );

        $strip = self::stripRange($range);

        return new self(
            totals: self::totals($listings, $earlier, $sold, $range, $previous),
            rows: self::rows($top, $sold, $range, $strip),
            stripDays: $strip->days,
        );
    }

    /**
     * The strip covers the range, capped so ninety bars never squeeze into
     * one table cell.
     */
    private static function stripRange(AnalyticsRange $range): AnalyticsRange
    {
        return AnalyticsRange::of(min($range->days, self::MAX_STRIP_DAYS), $range->end);
    }

    /**
     * @param  list<ListingTableRow>  $listings
     * @param  array<string, ListingEventCounts>  $earlier
     * @param  array<string, array<string, int>>  $sold  listing id => day => units
     * @return list<ActivityTotal>
     */
    private static function totals(array $listings, array $earlier, array $sold, AnalyticsRange $range, AnalyticsRange $previous): array
    {
        return [
            ActivityTotal::between(
                'Views',
                self::sum($listings, fn (ListingTableRow $row): int => $row->views),
                self::sumEarlier($earlier, fn (ListingEventCounts $counts): int => $counts->views),
            ),
            ActivityTotal::between(
                'Favorites',
                self::sum($listings, fn (ListingTableRow $row): int => $row->favorites),
                self::sumEarlier($earlier, fn (ListingEventCounts $counts): int => $counts->favorites),
            ),
            ActivityTotal::between(
                'Cart adds',
                self::sum($listings, fn (ListingTableRow $row): int => $row->cartAdds),
                self::sumEarlier($earlier, fn (ListingEventCounts $counts): int => $counts->cartAdds),
            ),
            ActivityTotal::between(
                'Sold',
                self::soldIn($sold, $range),
                self::soldIn($sold, $previous),
            ),
        ];
    }

    /**
     * @param  list<ListingTableRow>  $top
     * @param  array<string, array<string, int>>  $sold
     * @return list<OverviewListingRow>
     */
    private static function rows(array $top, array $sold, AnalyticsRange $range, AnalyticsRange $strip): array
    {
        /** @var list<string> $topIds */
        $topIds = array_map(fn (ListingTableRow $row): string => $row->id, $top);

        $views = AnalyticsReport::dailyViewsForListings($topIds, $strip->start, $strip->end);
        $days = $strip->dayLabels();

        return array_map(fn (ListingTableRow $row): OverviewListingRow => new OverviewListingRow(
            listing: $row,
            sold: array_sum(self::daysOf($sold[$row->id] ?? [], $range)),
            strip: BarStrip::bars(
                array_map(fn (string $day): int => $views[$row->id][$day] ?? 0, $days),
                $days,
                self::STRIP_HEIGHT_PX,
            ),
            href: route('seller.listings.show', ['listing' => $row->id]),
        ), $top);
    }

    /**
     * @param  list<ListingTableRow>  $listings
     * @param  callable(ListingTableRow): int  $figure
     */
    private static function sum(array $listings, callable $figure): int
    {
        return array_sum(array_map($figure, $listings));
    }

    /**
     * @param  array<string, ListingEventCounts>  $earlier
     * @param  callable(ListingEventCounts): int  $figure
     */
    private static function sumEarlier(array $earlier, callable $figure): int
    {
        return array_sum(array_map($figure, array_values($earlier)));
    }

    /**
     * @param  array<string, array<string, int>>  $sold
     */
    private static function soldIn(array $sold, AnalyticsRange $window): int
    {
        $units = 0;

        foreach ($sold as $byDay) {
            $units += array_sum(self::daysOf($byDay, $window));
        }

        return $units;
    }

    /**
     * @param  array<string, int>  $byDay
     * @return list<int>
     */
    private static function daysOf(array $byDay, AnalyticsRange $window): array
    {
        return array_map(fn (string $day): int => $byDay[$day] ?? 0, $window->dayLabels());
    }

    /**
     * Units of each listing sold between the two instants, by the UTC day
     * the order was placed on. An item counts when its order has been paid
     * and the seller's parcel on that order is neither declined nor
     * refunded — the pair {@see ListingTable} counts an all-time sale by.
     *
     * @return array<string, array<string, int>> listing id => day (Y-m-d) => units
     */
    private static function unitsSoldByListingAndDay(Seller $seller, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $rows = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.seller_id', $seller->id)
            ->whereIn('orders.status', Order::paidStatuses())
            ->where('orders.placed_at', '>=', $from)
            ->where('orders.placed_at', '<=', $to)
            ->whereExists(function (QueryBuilder $query): void {
                $query->selectRaw('1')
                    ->from('fulfillments')
                    ->whereColumn('fulfillments.order_id', 'order_items.order_id')
                    ->whereColumn('fulfillments.seller_id', 'order_items.seller_id')
                    ->whereNotIn('fulfillments.status', [FulfillmentStatus::Declined->value, FulfillmentStatus::Refunded->value]);
            })
            ->toBase()
            ->get(['order_items.listing_id as listing_id', 'order_items.quantity as quantity', 'orders.placed_at as placed_at']);

        $sold = [];

        foreach ($rows as $row) {
            $listingId = self::text($row->listing_id);
            $day = (new DateTimeImmutable(self::text($row->placed_at), new DateTimeZone('UTC')))->format('Y-m-d');
            $sold[$listingId][$day] = ($sold[$listingId][$day] ?? 0) + self::number($row->quantity);
        }

        return $sold;
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private static function number(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
