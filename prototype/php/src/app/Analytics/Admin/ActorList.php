<?php

declare(strict_types=1);

namespace App\Analytics\Admin;

use App\Domain\Analytics\ActorKindFilter;
use App\Domain\Analytics\ActorSort;
use App\Domain\Analytics\AnalyticsRange;
use App\Support\Page;

/**
 * `/admin/analytics/actors`, the drill-in's all-actors page: every actor
 * that carried an event in the range, sorted by {@see ActorSort} and
 * paged. {@see ActorAggregates} does the aggregation and the kind/search
 * narrowing this shares with {@see ActorLeaderboard}; paging happens in
 * PHP over the already-aggregated list. The number of distinct actors in
 * a range stays small enough at this prototype's scale that sorting and
 * slicing the whole list in memory costs nothing worth avoiding.
 */
final class ActorList
{
    public static function forRange(AnalyticsRange $range, ActorSort $sort, ActorKindFilter $kind, ?string $search, int $page, int $perPage = 25): ActorsPage
    {
        $summaries = ActorAggregates::forRange($range, $kind, $search);

        usort($summaries, fn (ActorSummary $a, ActorSummary $b): int => match ($sort) {
            ActorSort::Active => $b->events <=> $a->events ?: $a->id <=> $b->id,
            ActorSort::Recent => $b->lastSeenAt <=> $a->lastSeenAt ?: $a->id <=> $b->id,
        });

        $paged = Page::of((string) $page, $perPage, count($summaries));

        return new ActorsPage($paged, array_slice($summaries, $paged->offset, $paged->limit));
    }
}
