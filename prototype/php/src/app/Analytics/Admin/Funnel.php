<?php

declare(strict_types=1);

namespace App\Analytics\Admin;

use App\Domain\Analytics\AnalyticsEventName;
use App\Domain\Analytics\AnalyticsRange;
use App\Domain\Analytics\FunnelRate;
use App\Domain\Analytics\RangeChange;
use App\Models\Listing;
use App\Models\Seller;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The storefront funnel, visitors through paid orders — docs/analytics.md
 * names every step's definition. `forRange()` reads the whole store;
 * `forListing()` and `forSeller()` narrow every step to the events that
 * belong to one listing or one seller's listings.
 *
 * A listing view, favorite, or cart add belongs to a listing the way every
 * other admin analytics page already reads it: `subject_type = 'listing'`,
 * `subject_id` the listing's id. A checkout, order placement, order
 * payment, or order cancellation has no listing subject — its subject is
 * the cart or the order — so it belongs to a listing through the
 * `data.listing_ids` JSON array `App\Support\Orders\OrderListingIds` and
 * `Shop\CheckoutController::show` write onto it; this is the one place
 * that array is read back out, via SQLite's `json_each`.
 *
 * `visitors`, the funnel's first step, counts distinct `session_id`s among
 * every event that belongs to the scope — a listing's own view, favorite,
 * and cart-add rows plus the checkout and order rows whose listing ids
 * include it — the same scope every other step reads, so the rate below
 * it reads as "how many of the people who touched this listing bought",
 * not a share of the whole store's traffic.
 */
final class Funnel
{
    /** `analytics_events.name` values the funnel reads. `OrderCancel` is
     * queried alongside the rest but never becomes a step of its own — see
     * {@see cancelledNote()}. */
    private const array EVENT_NAMES = [
        AnalyticsEventName::ListingView,
        AnalyticsEventName::ListingFavorite,
        AnalyticsEventName::ListingCartAdd,
        AnalyticsEventName::CheckoutOpen,
        AnalyticsEventName::OrderPlace,
        AnalyticsEventName::OrderPay,
        AnalyticsEventName::OrderCancel,
    ];

    public static function forRange(AnalyticsRange $range): FunnelView
    {
        return self::build($range, null);
    }

    public static function forListing(string $listingId, AnalyticsRange $range): FunnelView
    {
        return self::build($range, [$listingId]);
    }

    /**
     * `$seller`'s listing ids come from the app database in one query.
     */
    public static function forSeller(Seller $seller, AnalyticsRange $range): FunnelView
    {
        /** @var list<string> $listingIds */
        $listingIds = Listing::query()->where('seller_id', $seller->id)->pluck('id')->all();

        return self::build($range, $listingIds);
    }

    /**
     * @param  list<string>|null  $listingIds  null reads every event in the
     *                                         range; a list narrows to events that belong to those listings.
     */
    private static function build(AnalyticsRange $range, ?array $listingIds): FunnelView
    {
        $previousRange = $range->previous();
        $byName = self::nameTotals($range, $previousRange, $listingIds);
        $visitors = self::visitorTotals($range, $previousRange, $listingIds);
        $cancelled = $byName[AnalyticsEventName::OrderCancel->value]['current'];

        $views = $byName[AnalyticsEventName::ListingView->value];
        $favorites = $byName[AnalyticsEventName::ListingFavorite->value];
        $cartAdds = $byName[AnalyticsEventName::ListingCartAdd->value];
        $checkoutsOpened = $byName[AnalyticsEventName::CheckoutOpen->value];
        $ordersPlaced = $byName[AnalyticsEventName::OrderPlace->value];
        $ordersPaid = $byName[AnalyticsEventName::OrderPay->value];

        return new FunnelView([
            self::step('Visitors', $visitors, null),
            self::step(AnalyticsEventName::ListingView->pluralLabel(), $views, $visitors['current']),
            self::step(AnalyticsEventName::ListingFavorite->pluralLabel(), $favorites, $views['current']),
            self::step(AnalyticsEventName::ListingCartAdd->pluralLabel(), $cartAdds, $favorites['current']),
            self::step(AnalyticsEventName::CheckoutOpen->pluralLabel(), $checkoutsOpened, $cartAdds['current']),
            self::step(AnalyticsEventName::OrderPlace->pluralLabel(), $ordersPlaced, $checkoutsOpened['current']),
            self::step(AnalyticsEventName::OrderPay->pluralLabel(), $ordersPaid, $ordersPlaced['current'], self::cancelledNote($cancelled)),
        ]);
    }

    /**
     * @param  array{current: int, previous: int}  $totals
     */
    private static function step(string $label, array $totals, ?int $ofPrevious, ?string $note = null): FunnelStep
    {
        return new FunnelStep(
            $label,
            $totals['current'],
            $totals['previous'],
            RangeChange::between($totals['current'], $totals['previous']),
            $ofPrevious === null ? null : FunnelRate::of($totals['current'], $ofPrevious),
            $note,
        );
    }

    private static function cancelledNote(int $cancelled): string
    {
        return number_format($cancelled).' cancelled';
    }

    /**
     * Every event name's current-versus-previous count in one pass over
     * `analytics_events`, the same shape {@see EventTotals} reads for the
     * entry page's events table.
     *
     * @param  list<string>|null  $listingIds
     * @return array<string, array{current: int, previous: int}>
     */
    private static function nameTotals(AnalyticsRange $range, AnalyticsRange $previousRange, ?array $listingIds): array
    {
        $currentStart = SqlInstant::format($range->start);

        $query = DB::connection('analytics')->table('analytics_events')
            ->whereIn('name', array_map(fn (AnalyticsEventName $name): string => $name->value, self::EVENT_NAMES))
            ->whereBetween('occurred_at', [SqlInstant::format($previousRange->start), SqlInstant::format($range->end)]);

        self::scopeToListings($query, $listingIds);

        $rows = $query
            ->select('name')
            ->selectRaw('sum(case when occurred_at >= ? then 1 else 0 end) as current', [$currentStart])
            ->selectRaw('sum(case when occurred_at < ? then 1 else 0 end) as previous', [$currentStart])
            ->groupBy('name')
            ->get();

        $totals = [];
        foreach (self::EVENT_NAMES as $name) {
            $totals[$name->value] = ['current' => 0, 'previous' => 0];
        }

        foreach ($rows as $row) {
            /** @var string $name */
            $name = $row->name;
            /** @var int|string $current */
            $current = $row->current;
            /** @var int|string $previous */
            $previous = $row->previous;

            $totals[$name] = ['current' => (int) $current, 'previous' => (int) $previous];
        }

        return $totals;
    }

    /**
     * Distinct `session_id`s in the current and the previous window, over
     * the same scope {@see nameTotals()} reads.
     *
     * @param  list<string>|null  $listingIds
     * @return array{current: int, previous: int}
     */
    private static function visitorTotals(AnalyticsRange $range, AnalyticsRange $previousRange, ?array $listingIds): array
    {
        $currentStart = SqlInstant::format($range->start);

        $query = DB::connection('analytics')->table('analytics_events')
            ->whereNotNull('session_id')
            ->whereBetween('occurred_at', [SqlInstant::format($previousRange->start), SqlInstant::format($range->end)]);

        self::scopeToListings($query, $listingIds);

        $row = $query
            ->selectRaw('count(distinct case when occurred_at >= ? then session_id end) as current', [$currentStart])
            ->selectRaw('count(distinct case when occurred_at < ? then session_id end) as previous', [$currentStart])
            ->first();

        /** @var int|string $current */
        $current = $row->current ?? 0;
        /** @var int|string $previousCount */
        $previousCount = $row->previous ?? 0;

        return ['current' => (int) $current, 'previous' => (int) $previousCount];
    }

    /**
     * Narrows a query to the events that belong to `$listingIds`: a
     * listing, favorite, or cart-add row naming one of them as its
     * subject, or a checkout or order row whose `data.listing_ids` JSON
     * array contains one of them. `null` leaves the query unscoped. An
     * empty list — a seller with no listings — matches nothing.
     *
     * @param  list<string>|null  $listingIds
     */
    private static function scopeToListings(Builder $query, ?array $listingIds): void
    {
        if ($listingIds === null) {
            return;
        }

        if ($listingIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $placeholders = implode(',', array_fill(0, count($listingIds), '?'));

        $query->where(function (Builder $scope) use ($listingIds, $placeholders): void {
            $scope->where(function (Builder $bySubject) use ($listingIds): void {
                $bySubject->where('subject_type', 'listing')->whereIn('subject_id', $listingIds);
            })->orWhereRaw(
                "exists (select 1 from json_each(data, '$.listing_ids') je where je.value in ({$placeholders}))",
                $listingIds,
            );
        });
    }
}
