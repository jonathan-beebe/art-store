<?php

declare(strict_types=1);

namespace App\Analytics\Admin;

use App\Domain\Analytics\ActorKindFilter;
use App\Domain\Analytics\ActorVelocity;
use App\Domain\Analytics\AnalyticsRange;
use App\Models\Customer;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;

/**
 * Every actor that carried an event in a range, aggregated once and read
 * by both {@see ActorLeaderboard} (sorted by peak, limited to a handful)
 * and {@see ActorList} (sorted by the all-actors page's own sort, paged)
 * — an internal collaborator the two share so neither can drift from the
 * other's numbers. Three queries against `analytics_events` build the
 * per-actor numbers — totals within the range, each actor's busiest UTC
 * hour within the range, and each actor's earliest event ever — then one
 * `Customer::whereIn()` fills in who each actor is. `$kind` and `$search`
 * both narrow the already-aggregated list in PHP: a search can match an
 * actor's id, its email, or any ip it used, and none of those live on the
 * same row the aggregate groups by.
 */
final class ActorAggregates
{
    /**
     * @return list<ActorSummary>
     */
    public static function forRange(AnalyticsRange $range, ActorKindFilter $kind, ?string $search): array
    {
        $totals = self::totalsByActor($range);

        if ($totals === []) {
            return [];
        }

        $peaks = self::peaksByActor($range);
        $firstSeen = self::firstSeenByActor(array_keys($totals));
        $identities = Customer::query()->whereIn('id', array_keys($totals))->get()->keyBy('id');

        $summaries = [];
        foreach ($totals as $actorId => $total) {
            $peak = $peaks[$actorId] ?? 0;
            $customer = $identities->get($actorId);
            // No matching row means the actor's customer was deleted after
            // it recorded events. It stays on the list, read as an
            // anonymous visitor who never signed in.
            $actorKind = 'anonymous';
            $actorWho = 'never signed in';

            if ($customer instanceof Customer) {
                $identity = ActorIdentity::of($customer);
                $actorKind = $identity->kind;
                $actorWho = $identity->who;
            }

            $summaries[] = new ActorSummary(
                $actorId,
                $actorKind,
                $actorWho,
                $total['ips'],
                $total['events'],
                $peak,
                $total['subjects'],
                // Every actor id here came from a row within the range, so
                // its own earliest-ever event always resolves.
                $firstSeen[$actorId] ?? $total['lastSeenAt'],
                $total['lastSeenAt'],
                ActorVelocity::flags($peak),
            );
        }

        return array_values(array_filter(
            $summaries,
            fn (ActorSummary $summary): bool => self::matchesKind($summary, $kind) && self::matchesSearch($summary, $search),
        ));
    }

    private static function matchesKind(ActorSummary $summary, ActorKindFilter $kind): bool
    {
        return match ($kind) {
            ActorKindFilter::All => true,
            ActorKindFilter::Anonymous => $summary->kind === 'anonymous',
            ActorKindFilter::Verified => $summary->kind === 'verified',
        };
    }

    private static function matchesSearch(ActorSummary $summary, ?string $search): bool
    {
        if ($search === null || trim($search) === '') {
            return true;
        }

        $needle = strtolower(trim($search));

        if (str_starts_with(strtolower($summary->id), $needle)) {
            return true;
        }

        if (str_contains(strtolower($summary->who), $needle)) {
            return true;
        }

        foreach ($summary->ips as $ip) {
            if (str_contains($ip, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, array{events: int, subjects: int, ips: list<string>, lastSeenAt: DateTimeImmutable}>
     */
    private static function totalsByActor(AnalyticsRange $range): array
    {
        $rows = DB::connection('analytics')->table('analytics_events')
            ->whereNotNull('actor_id')
            ->whereBetween('occurred_at', [SqlInstant::format($range->start), SqlInstant::format($range->end)])
            ->select('actor_id')
            ->selectRaw('count(*) as events')
            ->selectRaw('count(distinct subject_id) as subjects')
            ->selectRaw('max(occurred_at) as last_seen')
            ->selectRaw('group_concat(distinct ip) as ips')
            ->groupBy('actor_id')
            ->get();

        $totals = [];
        foreach ($rows as $row) {
            /** @var string $actorId */
            $actorId = $row->actor_id;
            /** @var int|string $events */
            $events = $row->events;
            /** @var int|string $subjects */
            $subjects = $row->subjects;
            /** @var string|null $ips */
            $ips = $row->ips;
            /** @var string $lastSeen */
            $lastSeen = $row->last_seen;

            $totals[$actorId] = [
                'events' => (int) $events,
                'subjects' => (int) $subjects,
                'ips' => $ips === null || $ips === '' ? [] : array_values(array_unique(explode(',', $ips))),
                'lastSeenAt' => new DateTimeImmutable($lastSeen, new DateTimeZone('UTC')),
            ];
        }

        return $totals;
    }

    /**
     * Each actor's busiest UTC hour in the range: an inner query tallies
     * events per actor per hour, an outer query takes the highest tally
     * each actor reached.
     *
     * @return array<string, int>
     */
    private static function peaksByActor(AnalyticsRange $range): array
    {
        $hourly = DB::connection('analytics')->table('analytics_events')
            ->whereNotNull('actor_id')
            ->whereBetween('occurred_at', [SqlInstant::format($range->start), SqlInstant::format($range->end)])
            ->select('actor_id')
            ->selectRaw("strftime('%Y-%m-%dT%H', occurred_at) as hour")
            ->selectRaw('count(*) as tally')
            ->groupBy('actor_id', 'hour');

        $rows = DB::connection('analytics')->query()
            ->fromSub($hourly, 'hourly')
            ->select('actor_id')
            ->selectRaw('max(tally) as peak')
            ->groupBy('actor_id')
            ->get();

        $peaks = [];
        foreach ($rows as $row) {
            /** @var string $actorId */
            $actorId = $row->actor_id;
            /** @var int|string $peak */
            $peak = $row->peak;

            $peaks[$actorId] = (int) $peak;
        }

        return $peaks;
    }

    /**
     * Each actor's earliest event ever, unbounded by any range — the
     * all-actors page's "First seen" column reads a longer history than
     * the totals and peaks above, which only ever look inside the range.
     *
     * @param  list<string>  $actorIds
     * @return array<string, DateTimeImmutable>
     */
    private static function firstSeenByActor(array $actorIds): array
    {
        $rows = DB::connection('analytics')->table('analytics_events')
            ->whereIn('actor_id', $actorIds)
            ->select('actor_id')
            ->selectRaw('min(occurred_at) as first_seen')
            ->groupBy('actor_id')
            ->get();

        $firstSeen = [];
        foreach ($rows as $row) {
            /** @var string $actorId */
            $actorId = $row->actor_id;
            /** @var string $firstSeenAt */
            $firstSeenAt = $row->first_seen;

            $firstSeen[$actorId] = new DateTimeImmutable($firstSeenAt, new DateTimeZone('UTC'));
        }

        return $firstSeen;
    }
}
