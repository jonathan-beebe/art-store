<?php

declare(strict_types=1);

namespace App\Analytics\Admin;

use App\Domain\Analytics\ActorKindFilter;
use App\Domain\Analytics\AnalyticsRange;

/**
 * The admin analytics entry page's actors-by-velocity table: every actor
 * that carried an event in the range, busiest UTC hour first, limited to
 * a handful — the full list is {@see ActorList}'s all-actors page.
 * {@see ActorAggregates} does the aggregation; this only orders and
 * limits its result.
 */
final class ActorLeaderboard
{
    /**
     * @return list<ActorSummary>
     */
    public static function forRange(AnalyticsRange $range, ActorKindFilter $kind, ?string $search, int $limit = 6): array
    {
        $summaries = ActorAggregates::forRange($range, $kind, $search);

        usort($summaries, fn (ActorSummary $a, ActorSummary $b): int => $b->peakPerHour <=> $a->peakPerHour);

        return array_slice($summaries, 0, $limit);
    }
}
