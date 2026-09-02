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
 * The admin analytics pages' actors-by-velocity table: every actor that
 * carried an event in the range, busiest UTC hour first. Two queries
 * against `analytics_events` build the raw per-actor numbers — one totals
 * query, one grouped by UTC hour to find each actor's peak — then one
 * `Customer::whereIn()` fills in who each actor is. `$kind` and `$search`
 * both narrow the already-aggregated list rather than the SQL: a search
 * can match an actor's id, its email, or any ip it used, and none of
 * those live on the same row the aggregate groups by.
 */
final class ActorLeaderboard
{
    /**
     * @return list<ActorSummary>
     */
    public static function forRange(AnalyticsRange $range, ActorKindFilter $kind, ?string $search, int $limit = 6): array
    {
        $totals = self::totalsByActor($range);
        $peaks = self::peaksByActor($range);

        if ($totals === []) {
            return [];
        }

        $identities = Customer::query()->whereIn('id', array_keys($totals))->get()->keyBy('id');

        $summaries = [];
        foreach ($totals as $actorId => $total) {
            $peak = $peaks[$actorId] ?? 0;
            $customer = $identities->get($actorId);
            // No matching row means the actor's customer was deleted after
            // it recorded events — read the same as an anonymous visitor
            // who never signed in, rather than dropped from the table.
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
                $total['lastSeenAt'],
                ActorVelocity::flags($peak),
            );
        }

        usort($summaries, fn (ActorSummary $a, ActorSummary $b): int => $b->peakPerHour <=> $a->peakPerHour);

        $filtered = array_values(array_filter(
            $summaries,
            fn (ActorSummary $summary): bool => self::matchesKind($summary, $kind) && self::matchesSearch($summary, $search),
        ));

        return array_slice($filtered, 0, $limit);
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
            ->whereBetween('occurred_at', [self::instant($range->start), self::instant($range->end)])
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
            ->whereBetween('occurred_at', [self::instant($range->start), self::instant($range->end)])
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

    private static function instant(DateTimeImmutable $moment): string
    {
        return $moment->format('Y-m-d H:i:s');
    }
}
