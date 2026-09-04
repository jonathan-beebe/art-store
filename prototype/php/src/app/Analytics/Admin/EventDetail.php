<?php

declare(strict_types=1);

namespace App\Analytics\Admin;

use App\Domain\Analytics\AnalyticsEventName;
use App\Domain\Analytics\AnalyticsRange;
use App\Domain\Analytics\ChangeDirection;
use App\Domain\Analytics\EventBreakdown;
use App\Domain\Analytics\PageViewSite;
use App\Domain\Analytics\RangeChange;
use App\Models\Customer;
use App\Models\Listing;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * The admin analytics event page's read: one event name's range tiles,
 * daily series, and breakdown. Every {@see AnalyticsEventName} case reads
 * `analytics_events`, grouped by listing, by actor, or by article per
 * `$by`; `page.view` reads the `page_view_counts` roll-up instead, which
 * carries no subject or actor of its own, so its breakdown is always by
 * route pattern and its "Distinct actors" tile reads "—".
 */
final class EventDetail
{
    public static function forRange(string $name, AnalyticsRange $range, EventBreakdown $by): EventDetailView
    {
        return $name === EventBreakdown::PAGE_VIEW_EVENT_NAME
            ? self::pageView($range)
            : self::listingEvent($name, $range, $by);
    }

    private static function listingEvent(string $name, AnalyticsRange $range, EventBreakdown $by): EventDetailView
    {
        $totals = self::totals($name, $range);
        $daily = self::dailyCounts($name, $range);
        $actors = self::distinctActors($name, $range);

        $rows = match ($by) {
            EventBreakdown::Actor => self::actorRows($name, $range, $totals['current']),
            EventBreakdown::Article => self::articleRows($name, $range, $totals['current']),
            default => self::listingRows($name, $range, $totals['current']),
        };

        return new EventDetailView(
            $name,
            AnalyticsEventName::from($name)->pluralLabel(),
            self::tiles($range, $totals['current'], $totals['previous'], $daily, $actors),
            $daily,
            AnalyticsRange::dayCaption($range->start->format('Y-m-d')),
            AnalyticsRange::dayCaption($range->end->format('Y-m-d')),
            $by,
            $rows,
        );
    }

    private static function pageView(AnalyticsRange $range): EventDetailView
    {
        $totals = self::pageViewTotals($range);
        $daily = self::pageViewDailyCounts($range);

        return new EventDetailView(
            EventBreakdown::PAGE_VIEW_EVENT_NAME,
            EventBreakdown::PAGE_VIEW_LABEL,
            self::tiles($range, $totals['current'], $totals['previous'], $daily, null),
            $daily,
            AnalyticsRange::dayCaption($range->start->format('Y-m-d')),
            AnalyticsRange::dayCaption($range->end->format('Y-m-d')),
            EventBreakdown::Pattern,
            self::patternRows($range, $totals['current']),
        );
    }

    /**
     * @param  list<int>  $daily
     * @param  array{total: int, anonymous: int}|null  $actors  null for the
     *                                                          `page.view` roll-up, which names no actor
     * @return list<EventTile>
     */
    private static function tiles(AnalyticsRange $range, int $current, int $previous, array $daily, ?array $actors): array
    {
        $change = RangeChange::between($current, $previous);
        $peakIndex = self::peakDayIndex($daily);
        $peakDay = $range->start->modify('+'.$peakIndex.' days');

        return [
            new EventTile('This range', number_format($current), $range->days.' days'),
            new EventTile('Previous', number_format($previous), 'the '.$range->days.' days before'),
            new EventTile('Change', $change->text, self::changeNote($change)),
            new EventTile('Busiest day', number_format($daily[$peakIndex] ?? 0), AnalyticsRange::dayCaption($peakDay->format('Y-m-d'))),
            new EventTile(
                'Distinct actors',
                $actors === null ? '—' : number_format($actors['total']),
                $actors === null ? '—' : number_format($actors['anonymous']).' anonymous',
            ),
        ];
    }

    /**
     * The earliest day carrying the daily series' highest count — 0 for an
     * empty series, which never happens in practice since every range spans
     * at least a week.
     *
     * @param  list<int>  $daily
     */
    private static function peakDayIndex(array $daily): int
    {
        if ($daily === []) {
            return 0;
        }

        $index = array_search(max($daily), $daily, true);

        return is_int($index) ? $index : 0;
    }

    private static function changeNote(RangeChange $change): string
    {
        return match (true) {
            $change->text === 'new' => 'nothing in the previous range',
            $change->direction === ChangeDirection::Up => 'up on the previous range',
            $change->direction === ChangeDirection::Down => 'down on the previous range',
            default => 'flat against the previous range',
        };
    }

    /**
     * @return array{current: int, previous: int}
     */
    private static function totals(string $name, AnalyticsRange $range): array
    {
        $previous = $range->previous();
        $currentStart = SqlInstant::format($range->start);

        $row = DB::connection('analytics')->table('analytics_events')
            ->where('name', $name)
            ->whereBetween('occurred_at', [SqlInstant::format($previous->start), SqlInstant::format($range->end)])
            ->selectRaw('sum(case when occurred_at >= ? then 1 else 0 end) as current', [$currentStart])
            ->selectRaw('sum(case when occurred_at < ? then 1 else 0 end) as previous', [$currentStart])
            ->first();

        return self::currentPreviousFromRow($row);
    }

    /**
     * @return list<int>
     */
    private static function dailyCounts(string $name, AnalyticsRange $range): array
    {
        $rows = DB::connection('analytics')->table('analytics_events')
            ->where('name', $name)
            ->whereBetween('occurred_at', [SqlInstant::format($range->start), SqlInstant::format($range->end)])
            ->selectRaw('date(occurred_at) as day')
            ->selectRaw('count(*) as tally')
            ->groupBy('day')
            ->get();

        $byDay = [];
        foreach ($rows as $row) {
            /** @var string $day */
            $day = $row->day;
            /** @var int|string $tally */
            $tally = $row->tally;

            $byDay[$day] = (int) $tally;
        }

        return array_map(fn (string $day): int => $byDay[$day] ?? 0, $range->dayLabels());
    }

    /**
     * @return array{total: int, anonymous: int}
     */
    private static function distinctActors(string $name, AnalyticsRange $range): array
    {
        $actorIds = DB::connection('analytics')->table('analytics_events')
            ->where('name', $name)
            ->whereNotNull('actor_id')
            ->whereBetween('occurred_at', [SqlInstant::format($range->start), SqlInstant::format($range->end)])
            ->distinct()
            ->pluck('actor_id');

        if ($actorIds->isEmpty()) {
            return ['total' => 0, 'anonymous' => 0];
        }

        $verified = Customer::query()->whereIn('id', $actorIds)->get()
            ->filter(fn (Customer $customer): bool => ActorIdentity::of($customer)->kind === 'verified')
            ->count();

        return ['total' => $actorIds->count(), 'anonymous' => $actorIds->count() - $verified];
    }

    /**
     * @return array<string, array{current: int, previous: int}>
     */
    private static function subjectTotals(string $name, AnalyticsRange $range, string $subjectType): array
    {
        $previous = $range->previous();
        $currentStart = SqlInstant::format($range->start);

        $rows = DB::connection('analytics')->table('analytics_events')
            ->where('name', $name)
            ->where('subject_type', $subjectType)
            ->whereNotNull('subject_id')
            ->whereBetween('occurred_at', [SqlInstant::format($previous->start), SqlInstant::format($range->end)])
            ->select('subject_id')
            ->selectRaw('sum(case when occurred_at >= ? then 1 else 0 end) as current', [$currentStart])
            ->selectRaw('sum(case when occurred_at < ? then 1 else 0 end) as previous', [$currentStart])
            ->groupBy('subject_id')
            ->get();

        $totals = [];
        foreach ($rows as $row) {
            /** @var string $subjectId */
            $subjectId = $row->subject_id;
            /** @var int|string $current */
            $current = $row->current;
            /** @var int|string $previous */
            $previous = $row->previous;

            $totals[$subjectId] = ['current' => (int) $current, 'previous' => (int) $previous];
        }

        return $totals;
    }

    /**
     * @return array<string, array{current: int, previous: int}>
     */
    private static function actorTotals(string $name, AnalyticsRange $range): array
    {
        $previous = $range->previous();
        $currentStart = SqlInstant::format($range->start);

        $rows = DB::connection('analytics')->table('analytics_events')
            ->where('name', $name)
            ->whereNotNull('actor_id')
            ->whereBetween('occurred_at', [SqlInstant::format($previous->start), SqlInstant::format($range->end)])
            ->select('actor_id')
            ->selectRaw('sum(case when occurred_at >= ? then 1 else 0 end) as current', [$currentStart])
            ->selectRaw('sum(case when occurred_at < ? then 1 else 0 end) as previous', [$currentStart])
            ->groupBy('actor_id')
            ->get();

        $totals = [];
        foreach ($rows as $row) {
            /** @var string $actorId */
            $actorId = $row->actor_id;
            /** @var int|string $current */
            $current = $row->current;
            /** @var int|string $previous */
            $previous = $row->previous;

            $totals[$actorId] = ['current' => (int) $current, 'previous' => (int) $previous];
        }

        return $totals;
    }

    /**
     * @return list<EventBreakdownRow>
     */
    private static function listingRows(string $name, AnalyticsRange $range, int $eventTotal): array
    {
        $totals = self::subjectTotals($name, $range, 'listing');

        if ($totals === []) {
            return [];
        }

        $listings = Listing::query()->whereIn('id', array_keys($totals))->with('seller')->get()->keyBy('id');

        $rows = [];
        foreach ($totals as $listingId => $counts) {
            $listing = $listings->get($listingId);
            $title = $listing instanceof Listing
                ? $listing->title.' · '.$listing->seller->displayName()
                : 'listing no longer exists';

            $rows[] = self::row($listingId, $title, null, null, $counts, $eventTotal);
        }

        return self::sortedByCurrentDesc($rows);
    }

    /**
     * The article breakdown's rows: one per article slug the event's rows
     * name, labelled by the slug itself.
     *
     * @return list<EventBreakdownRow>
     */
    private static function articleRows(string $name, AnalyticsRange $range, int $eventTotal): array
    {
        $totals = self::subjectTotals($name, $range, 'help_article');

        if ($totals === []) {
            return [];
        }

        $rows = [];
        foreach ($totals as $slug => $counts) {
            $rows[] = self::row($slug, $slug, null, null, $counts, $eventTotal);
        }

        return self::sortedByCurrentDesc($rows);
    }

    /**
     * @return list<EventBreakdownRow>
     */
    private static function actorRows(string $name, AnalyticsRange $range, int $eventTotal): array
    {
        $totals = self::actorTotals($name, $range);

        if ($totals === []) {
            return [];
        }

        $customers = Customer::query()->whereIn('id', array_keys($totals))->get()->keyBy('id');

        $rows = [];
        foreach ($totals as $actorId => $counts) {
            $customer = $customers->get($actorId);
            // No matching row means the actor's customer was deleted after it
            // recorded events — read the same as an anonymous visitor who
            // never signed in, the way ActorLeaderboard does.
            $kind = 'anonymous';
            $who = 'never signed in';

            if ($customer instanceof Customer) {
                $identity = ActorIdentity::of($customer);
                $kind = $identity->kind;
                $who = $identity->who;
            }

            $rows[] = self::row($actorId, $who, $kind, null, $counts, $eventTotal);
        }

        return self::sortedByCurrentDesc($rows);
    }

    /**
     * @return array{current: int, previous: int}
     */
    private static function pageViewTotals(AnalyticsRange $range): array
    {
        $previous = $range->previous();
        $currentStartDay = $range->start->format('Y-m-d');

        $row = DB::connection('analytics')->table('page_view_counts')
            ->whereBetween('day', [$previous->start->format('Y-m-d'), $range->end->format('Y-m-d')])
            ->selectRaw('sum(case when day >= ? then count else 0 end) as current', [$currentStartDay])
            ->selectRaw('sum(case when day < ? then count else 0 end) as previous', [$currentStartDay])
            ->first();

        return self::currentPreviousFromRow($row);
    }

    /**
     * @return list<int>
     */
    private static function pageViewDailyCounts(AnalyticsRange $range): array
    {
        $rows = DB::connection('analytics')->table('page_view_counts')
            ->whereBetween('day', [$range->start->format('Y-m-d'), $range->end->format('Y-m-d')])
            ->select('day')
            ->selectRaw('sum(count) as tally')
            ->groupBy('day')
            ->get();

        $byDay = [];
        foreach ($rows as $row) {
            /** @var string $day */
            $day = $row->day;
            /** @var int|string $tally */
            $tally = $row->tally;

            $byDay[$day] = (int) $tally;
        }

        return array_map(fn (string $day): int => $byDay[$day] ?? 0, $range->dayLabels());
    }

    /**
     * @return list<EventBreakdownRow>
     */
    private static function patternRows(AnalyticsRange $range, int $eventTotal): array
    {
        $previous = $range->previous();
        $currentStartDay = $range->start->format('Y-m-d');

        $rows = DB::connection('analytics')->table('page_view_counts')
            ->whereBetween('day', [$previous->start->format('Y-m-d'), $range->end->format('Y-m-d')])
            ->select('site', 'path_pattern')
            ->selectRaw('sum(case when day >= ? then count else 0 end) as current', [$currentStartDay])
            ->selectRaw('sum(case when day < ? then count else 0 end) as previous', [$currentStartDay])
            ->groupBy('site', 'path_pattern')
            ->get();

        $built = [];
        foreach ($rows as $row) {
            /** @var string $site */
            $site = $row->site;
            /** @var string $pattern */
            $pattern = $row->path_pattern;
            /** @var int|string $current */
            $current = $row->current;
            /** @var int|string $previous */
            $previous = $row->previous;

            $built[] = self::row($pattern, $pattern, null, PageViewSite::from($site), ['current' => (int) $current, 'previous' => (int) $previous], $eventTotal);
        }

        return self::sortedByCurrentDesc($built);
    }

    /**
     * @param  array{current: int, previous: int}  $counts
     */
    private static function row(string $id, string $title, ?string $actorKind, ?PageViewSite $site, array $counts, int $eventTotal): EventBreakdownRow
    {
        $share = $eventTotal > 0 ? $counts['current'] / $eventTotal : 0.0;

        return new EventBreakdownRow(
            $id,
            $title,
            $actorKind,
            $site,
            $counts['current'],
            $counts['previous'],
            RangeChange::between($counts['current'], $counts['previous']),
            ((int) round($share * 100)).'%',
            (int) round($share * 100),
        );
    }

    /**
     * @param  list<EventBreakdownRow>  $rows
     * @return list<EventBreakdownRow>
     */
    private static function sortedByCurrentDesc(array $rows): array
    {
        usort($rows, fn (EventBreakdownRow $a, EventBreakdownRow $b): int => $b->current <=> $a->current);

        return $rows;
    }

    /**
     * @return array{current: int, previous: int}
     */
    private static function currentPreviousFromRow(?stdClass $row): array
    {
        if ($row === null) {
            return ['current' => 0, 'previous' => 0];
        }

        /** @var int|string|null $current */
        $current = $row->current;
        /** @var int|string|null $previous */
        $previous = $row->previous;

        return [
            'current' => $current === null ? 0 : (int) $current,
            'previous' => $previous === null ? 0 : (int) $previous,
        ];
    }
}
