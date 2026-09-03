<?php

declare(strict_types=1);

namespace App\Analytics;

use App\Domain\Analytics\AnalyticsEventName;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;

/**
 * Reads the rows {@see Analytics} writes to `analytics_events`. Every
 * method here queries the analytics connection directly and is unguarded:
 * an unavailable store surfaces as whatever error the connection throws,
 * the way a missing data source reads anywhere else in the app. Page-view
 * totals stay on {@see \App\Models\PageViewCount} — this class does not
 * duplicate them.
 */
final class AnalyticsReport
{
    /**
     * How many views, favorites, and cart-adds one listing has recorded —
     * the seller and admin listing-detail pages' source.
     */
    public static function countsForListing(string $listingId): ListingEventCounts
    {
        $counts = self::tallyByName(
            fn ($query) => $query->where('subject_type', 'listing')->where('subject_id', $listingId),
        );

        return new ListingEventCounts(
            views: $counts[AnalyticsEventName::ListingView->value] ?? 0,
            favorites: $counts[AnalyticsEventName::ListingFavorite->value] ?? 0,
            cartAdds: $counts[AnalyticsEventName::ListingCartAdd->value] ?? 0,
        );
    }

    /**
     * How many events of each name one listing recorded on each day from
     * `$from` onward — the seller listing-detail page's activity timeline
     * source.
     *
     * @return array<string, array<string, int>> day (Y-m-d) => event name => count
     */
    public static function dailyCountsForListingSince(string $listingId, DateTimeImmutable $from): array
    {
        $rows = DB::connection('analytics')->table('analytics_events')
            ->where('subject_type', 'listing')
            ->where('subject_id', $listingId)
            ->where('occurred_at', '>=', $from->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'))
            ->select('name')
            ->selectRaw('date(occurred_at) as day')
            ->selectRaw('count(*) as tally')
            ->groupBy('day', 'name')
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            /** @var string $day */
            $day = $row->day;
            /** @var string $name */
            $name = $row->name;
            /** @var int|string $tally */
            $tally = $row->tally;

            $counts[$day][$name] = (int) $tally;
        }

        return $counts;
    }

    /**
     * How many events of each name the whole platform has recorded, across
     * every subject — {@see \App\Http\Controllers\Admin\StatsController}'s
     * tally.
     *
     * @return array<string, int> event name => count
     */
    public static function platformCountsByName(): array
    {
        return self::tallyByName(fn ($query) => $query);
    }

    /**
     * @param  callable(\Illuminate\Database\Query\Builder): \Illuminate\Database\Query\Builder  $scope
     * @return array<string, int> event name => count
     */
    private static function tallyByName(callable $scope): array
    {
        $rows = $scope(DB::connection('analytics')->table('analytics_events'))
            ->select('name')
            ->selectRaw('count(*) as tally')
            ->groupBy('name')
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            /** @var string $name */
            $name = $row->name;
            /** @var int|string $tally */
            $tally = $row->tally;

            $counts[$name] = (int) $tally;
        }

        return $counts;
    }
}
