<?php

declare(strict_types=1);

namespace App\Analytics\Admin;

use App\Domain\Analytics\AnalyticsEventName;
use App\Domain\Analytics\AnalyticsRange;
use App\Domain\Analytics\EventBreakdown;
use App\Domain\Analytics\RangeChange;
use Illuminate\Support\Facades\DB;

/**
 * The admin analytics entry page's events table: one row per
 * {@see AnalyticsEventName} case plus one roll-up row for `page.view`,
 * each carrying its range comparison and daily series.
 * `analytics_events` and `page_view_counts` are queried two ways each —
 * one grouped query for the current-versus-previous totals (and, for
 * `analytics_events`, the distinct subject/actor counts alongside them),
 * one grouped by day for the range's bar strip — so the page never issues
 * a query per event name.
 */
final class EventTotals
{
    /**
     * @return list<EventTotal>
     */
    public static function forRange(AnalyticsRange $range): array
    {
        return [
            ...self::listingEventTotals($range),
            self::pageViewTotal($range),
        ];
    }

    /**
     * @return list<EventTotal>
     */
    private static function listingEventTotals(AnalyticsRange $range): array
    {
        $byName = self::nameTotals($range);
        $dailyByName = self::dailyTotalsByName($range);
        $dayLabels = $range->dayLabels();

        return array_map(
            fn (AnalyticsEventName $case): EventTotal => self::eventTotal(
                $case->value,
                $case->pluralLabel(),
                $byName[$case->value] ?? ['current' => 0, 'previous' => 0, 'subjects' => 0, 'actors' => 0],
                $dailyByName[$case->value] ?? [],
                $dayLabels,
            ),
            AnalyticsEventName::cases(),
        );
    }

    /**
     * @param  array{current: int, previous: int, subjects: int, actors: int}  $totals
     * @param  array<string, int>  $dailyForName
     * @param  list<string>  $dayLabels
     */
    private static function eventTotal(string $name, string $label, array $totals, array $dailyForName, array $dayLabels): EventTotal
    {
        $daily = array_map(fn (string $day): int => $dailyForName[$day] ?? 0, $dayLabels);

        return new EventTotal(
            $name,
            $label,
            $totals['current'],
            $totals['previous'],
            RangeChange::between($totals['current'], $totals['previous']),
            $daily,
            $totals['subjects'],
            $totals['actors'],
        );
    }

    /**
     * One row per event name, covering both the current and the previous
     * window in a single pass over `analytics_events` — `current`,
     * `previous`, and the current window's distinct subject/actor counts
     * all bucket on the same `occurred_at >= $currentStart` test.
     *
     * @return array<string, array{current: int, previous: int, subjects: int, actors: int}>
     */
    private static function nameTotals(AnalyticsRange $range): array
    {
        $previous = $range->previous();
        $currentStart = SqlInstant::format($range->start);

        $rows = DB::connection('analytics')->table('analytics_events')
            ->whereBetween('occurred_at', [SqlInstant::format($previous->start), SqlInstant::format($range->end)])
            ->select('name')
            ->selectRaw('sum(case when occurred_at >= ? then 1 else 0 end) as current', [$currentStart])
            ->selectRaw('sum(case when occurred_at < ? then 1 else 0 end) as previous', [$currentStart])
            ->selectRaw('count(distinct case when occurred_at >= ? then subject_id end) as subjects', [$currentStart])
            ->selectRaw('count(distinct case when occurred_at >= ? then actor_id end) as actors', [$currentStart])
            ->groupBy('name')
            ->get();

        $totals = [];
        foreach ($rows as $row) {
            /** @var string $name */
            $name = $row->name;
            /** @var int|string $current */
            $current = $row->current;
            /** @var int|string $previous */
            $previous = $row->previous;
            /** @var int|string $subjects */
            $subjects = $row->subjects;
            /** @var int|string $actors */
            $actors = $row->actors;

            $totals[$name] = [
                'current' => (int) $current,
                'previous' => (int) $previous,
                'subjects' => (int) $subjects,
                'actors' => (int) $actors,
            ];
        }

        return $totals;
    }

    /**
     * Every event name's count on every day of the current range.
     *
     * @return array<string, array<string, int>> name => (Y-m-d => count)
     */
    private static function dailyTotalsByName(AnalyticsRange $range): array
    {
        $rows = DB::connection('analytics')->table('analytics_events')
            ->whereBetween('occurred_at', [SqlInstant::format($range->start), SqlInstant::format($range->end)])
            ->select('name')
            ->selectRaw('date(occurred_at) as day')
            ->selectRaw('count(*) as tally')
            ->groupBy('name', 'day')
            ->get();

        $daily = [];
        foreach ($rows as $row) {
            /** @var string $name */
            $name = $row->name;
            /** @var string $day */
            $day = $row->day;
            /** @var int|string $tally */
            $tally = $row->tally;

            $daily[$name][$day] = (int) $tally;
        }

        return $daily;
    }

    private static function pageViewTotal(AnalyticsRange $range): EventTotal
    {
        $previous = $range->previous();
        $currentStartDay = $range->start->format('Y-m-d');

        $totalsRow = DB::connection('analytics')->table('page_view_counts')
            ->whereBetween('day', [$previous->start->format('Y-m-d'), $range->end->format('Y-m-d')])
            ->selectRaw('sum(case when day >= ? then count else 0 end) as current', [$currentStartDay])
            ->selectRaw('sum(case when day < ? then count else 0 end) as previous', [$currentStartDay])
            ->first();

        $current = 0;
        $previousCount = 0;

        if ($totalsRow !== null) {
            /** @var int|string $currentTally */
            $currentTally = $totalsRow->current;
            /** @var int|string $previousTally */
            $previousTally = $totalsRow->previous;

            $current = (int) $currentTally;
            $previousCount = (int) $previousTally;
        }

        $dailyRows = DB::connection('analytics')->table('page_view_counts')
            ->whereBetween('day', [$range->start->format('Y-m-d'), $range->end->format('Y-m-d')])
            ->select('day')
            ->selectRaw('sum(count) as tally')
            ->groupBy('day')
            ->get();

        $dailyByDay = [];
        foreach ($dailyRows as $row) {
            /** @var string $day */
            $day = $row->day;
            /** @var int|string $tally */
            $tally = $row->tally;

            $dailyByDay[$day] = (int) $tally;
        }

        $daily = array_map(fn (string $day): int => $dailyByDay[$day] ?? 0, $range->dayLabels());

        return new EventTotal(
            EventBreakdown::PAGE_VIEW_EVENT_NAME,
            EventBreakdown::PAGE_VIEW_LABEL,
            $current,
            $previousCount,
            RangeChange::between($current, $previousCount),
            $daily,
            null,
            null,
        );
    }
}
