<?php

declare(strict_types=1);

namespace App\Analytics\Admin;

use App\Domain\Analytics\AnalyticsEventName;
use App\Domain\Analytics\AnalyticsRange;
use App\Domain\Analytics\FunnelDefinition;
use App\Domain\Analytics\FunnelRate;
use App\Domain\Analytics\FunnelShare;
use App\Domain\Analytics\RangeChange;
use App\Models\Listing;
use App\Models\Seller;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * A funnel's steps for a range, a listing, or a seller — docs/analytics.md
 * § "The funnel" names every scope's own read. `forRange()` reads the whole
 * store; `forListing()` and `forSeller()` narrow every step to the events
 * that belong to one listing or one seller's listings.
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
 * `visitors`, a funnel's implied first step, counts distinct `session_id`s
 * among every event that belongs to the scope, null session ids excluded.
 * Every other step counts distinct sessions among the events that carry its
 * own name and a session id, so a step's count never exceeds the one
 * before it. Its rate is read against the step immediately before it in
 * the definition's ordered list, visitors for the first named step.
 */
final class Funnel
{
    private const string VISITORS_KEY = 'visitors';

    private const string VISITORS_LABEL = 'Visitors';

    public static function forRange(FunnelDefinition $definition, AnalyticsRange $range): FunnelView
    {
        return self::build($definition, $range, null);
    }

    public static function forListing(FunnelDefinition $definition, string $listingId, AnalyticsRange $range): FunnelView
    {
        return self::build($definition, $range, [$listingId]);
    }

    /**
     * `$seller`'s listing ids come from the app database in one query.
     */
    public static function forSeller(FunnelDefinition $definition, Seller $seller, AnalyticsRange $range): FunnelView
    {
        /** @var list<string> $listingIds */
        $listingIds = Listing::query()->where('seller_id', $seller->id)->pluck('id')->all();

        return self::build($definition, $range, $listingIds);
    }

    /**
     * @param  list<string>|null  $listingIds  null reads every event in the
     *                                         range; a list narrows to events that belong to those listings.
     */
    private static function build(FunnelDefinition $definition, AnalyticsRange $range, ?array $listingIds): FunnelView
    {
        $previousRange = $range->previous();
        $sessionsByName = self::sessionsByName($range, $previousRange, $listingIds, self::namesToQuery($definition));
        $visitors = self::visitorTotals($range, $previousRange, $listingIds);

        $drafts = self::drafts($definition, $sessionsByName, $visitors['current']);
        $largestDropIndex = self::largestDropIndex($drafts);

        $steps = [self::visitorsStep($visitors)];

        foreach ($drafts as $index => $draft) {
            $steps[] = self::toStep($draft, $visitors, $sessionsByName, $index === $largestDropIndex);
        }

        return new FunnelView($steps);
    }

    /**
     * Every named step's totals and its rate against its own prerequisite —
     * the step immediately before it, visitors for the first one — built
     * once so {@see largestDropIndex()} can read every rate before any
     * {@see FunnelStep} is constructed.
     *
     * @param  array<string, array{current: int, previous: int}>  $sessionsByName
     * @return list<array{name: AnalyticsEventName, totals: array{current: int, previous: int}, rate: ?FunnelRate}>
     */
    private static function drafts(FunnelDefinition $definition, array $sessionsByName, int $visitorsCurrent): array
    {
        $drafts = [];
        $prerequisiteCurrent = $visitorsCurrent;
        $prerequisiteLabel = strtolower(self::VISITORS_LABEL);

        foreach ($definition->steps as $name) {
            $totals = $sessionsByName[$name->value];

            $drafts[] = [
                'name' => $name,
                'totals' => $totals,
                'rate' => FunnelRate::of($totals['current'], $prerequisiteCurrent, $prerequisiteLabel),
            ];

            $prerequisiteCurrent = $totals['current'];
            $prerequisiteLabel = strtolower($name->pluralLabel());
        }

        return $drafts;
    }

    /**
     * The index of the one draft whose rate is the lowest among every draft
     * that carries one — null when none does.
     *
     * @param  list<array{name: AnalyticsEventName, totals: array{current: int, previous: int}, rate: ?FunnelRate}>  $drafts
     */
    private static function largestDropIndex(array $drafts): ?int
    {
        $lowestIndex = null;
        $lowestRatio = null;

        foreach ($drafts as $index => $draft) {
            if ($draft['rate'] === null) {
                continue;
            }

            if ($lowestRatio === null || $draft['rate']->ratio < $lowestRatio) {
                $lowestRatio = $draft['rate']->ratio;
                $lowestIndex = $index;
            }
        }

        return $lowestIndex;
    }

    /**
     * @param  array{name: AnalyticsEventName, totals: array{current: int, previous: int}, rate: ?FunnelRate}  $draft
     * @param  array{current: int, previous: int}  $visitors
     * @param  array<string, array{current: int, previous: int}>  $sessionsByName
     */
    private static function toStep(array $draft, array $visitors, array $sessionsByName, bool $isLargestDrop): FunnelStep
    {
        $name = $draft['name'];
        $totals = $draft['totals'];

        return new FunnelStep(
            $name->value,
            $name->pluralLabel(),
            $totals['current'],
            $totals['previous'],
            RangeChange::between($totals['current'], $totals['previous']),
            $draft['rate'],
            FunnelShare::of($totals['current'], $visitors['current']),
            FunnelShare::of($totals['previous'], $visitors['previous']),
            $isLargestDrop,
            $name === AnalyticsEventName::OrderPay ? self::cancelledNote($sessionsByName[AnalyticsEventName::OrderCancel->value]['current']) : null,
            $name === AnalyticsEventName::ListingView ? self::favoritedSide($sessionsByName[AnalyticsEventName::ListingFavorite->value]['current']) : null,
        );
    }

    /**
     * @param  array{current: int, previous: int}  $visitors
     */
    private static function visitorsStep(array $visitors): FunnelStep
    {
        return new FunnelStep(
            self::VISITORS_KEY,
            self::VISITORS_LABEL,
            $visitors['current'],
            $visitors['previous'],
            RangeChange::between($visitors['current'], $visitors['previous']),
            null,
            FunnelShare::of($visitors['current'], $visitors['current']),
            FunnelShare::of($visitors['previous'], $visitors['previous']),
            false,
        );
    }

    private static function cancelledNote(int $cancelled): string
    {
        return number_format($cancelled).' cancelled';
    }

    private static function favoritedSide(int $favorited): string
    {
        return number_format($favorited).' favorited';
    }

    /**
     * The names {@see sessionsByName()} must read: every step in the
     * definition, plus order cancellations when orders paid is a step (the
     * paid step's note) and favorites when listing views is a step (the
     * viewed step's side count).
     *
     * @return list<AnalyticsEventName>
     */
    private static function namesToQuery(FunnelDefinition $definition): array
    {
        $names = $definition->steps;
        $extra = [];

        if (in_array(AnalyticsEventName::OrderPay, $names, true) && ! in_array(AnalyticsEventName::OrderCancel, $names, true)) {
            $extra[] = AnalyticsEventName::OrderCancel;
        }

        if (in_array(AnalyticsEventName::ListingView, $names, true) && ! in_array(AnalyticsEventName::ListingFavorite, $names, true)) {
            $extra[] = AnalyticsEventName::ListingFavorite;
        }

        return [...$names, ...$extra];
    }

    /**
     * Distinct `session_id`s per name, current and previous window, over the
     * scope's own events — the shape {@see visitorTotals()} reads for the
     * funnel's first step, grouped by name in one query, so the funnel
     * never issues a query per step.
     *
     * @param  list<string>|null  $listingIds
     * @param  list<AnalyticsEventName>  $names
     * @return array<string, array{current: int, previous: int}>
     */
    private static function sessionsByName(AnalyticsRange $range, AnalyticsRange $previousRange, ?array $listingIds, array $names): array
    {
        $currentStart = SqlInstant::format($range->start);

        $query = DB::connection('analytics')->table('analytics_events')
            ->whereIn('name', array_map(fn (AnalyticsEventName $name): string => $name->value, $names))
            ->whereNotNull('session_id')
            ->whereBetween('occurred_at', [SqlInstant::format($previousRange->start), SqlInstant::format($range->end)]);

        self::scopeToListings($query, $listingIds);

        $rows = $query
            ->select('name')
            ->selectRaw('count(distinct case when occurred_at >= ? then session_id end) as current', [$currentStart])
            ->selectRaw('count(distinct case when occurred_at < ? then session_id end) as previous', [$currentStart])
            ->groupBy('name')
            ->get();

        $totals = [];
        foreach ($names as $name) {
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
     * the same scope {@see sessionsByName()} reads.
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
