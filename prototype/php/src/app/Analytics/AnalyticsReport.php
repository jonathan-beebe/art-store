<?php

declare(strict_types=1);

namespace App\Analytics;

use App\Domain\Analytics\AnalyticsEventName;
use App\Domain\Analytics\Channel;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Reads the rows {@see Analytics} writes to `analytics_events` and
 * `analytics_visits`. Every method here queries the analytics connection
 * directly and is unguarded: an unavailable store surfaces as whatever
 * error the connection throws, the way a missing data source reads
 * anywhere else in the app. Page-view totals stay on
 * {@see \App\Models\PageViewCount} — this class does not duplicate them.
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
     * Everything one ip has done since `$from`, newest first — how an
     * operator isolates a scripted or abusive visitor once its cookie is
     * no longer the only trace of it.
     *
     * @return list<AnalyticsEventRow>
     */
    public static function eventsForIp(string $ip, DateTimeImmutable $from): array
    {
        return self::eventRows(fn ($query) => $query->where('ip', $ip), $from);
    }

    /**
     * Everything one browser session has done since `$from`, newest first —
     * the same isolation as {@see eventsForIp()}, across every ip that
     * session used.
     *
     * @return list<AnalyticsEventRow>
     */
    public static function eventsForSession(string $sessionId, DateTimeImmutable $from): array
    {
        return self::eventRows(fn ($query) => $query->where('session_id', $sessionId), $from);
    }

    /**
     * Every visit an actor's own first-touch rows carry, newest first, each
     * with the {@see Channel} it derives to — an actor's own page's source
     * for the origin of each of their visits.
     *
     * @return list<ActorVisitRow>
     */
    public static function visitsForActor(string $actorId): array
    {
        $rows = DB::connection('analytics')->table('analytics_visits')
            ->where('actor_id', $actorId)
            ->orderByDesc('first_seen_at')
            ->get();

        return array_values($rows->map(self::toActorVisitRow(...))->all());
    }

    private static function toActorVisitRow(stdClass $row): ActorVisitRow
    {
        /** @var string $sessionId */
        $sessionId = $row->session_id;
        /** @var string $firstSeenAt */
        $firstSeenAt = $row->first_seen_at;
        /** @var string $landingPath */
        $landingPath = $row->landing_path;
        /** @var string|null $utmSource */
        $utmSource = $row->utm_source;
        /** @var string|null $utmMedium */
        $utmMedium = $row->utm_medium;
        /** @var string|null $utmCampaign */
        $utmCampaign = $row->utm_campaign;
        /** @var string|null $referrerHost */
        $referrerHost = $row->referrer_host;

        return new ActorVisitRow(
            $sessionId,
            new DateTimeImmutable($firstSeenAt, new DateTimeZone('UTC')),
            $landingPath,
            Channel::derive($utmSource, $utmMedium, $utmCampaign, $referrerHost),
        );
    }

    /**
     * @param  callable(\Illuminate\Database\Query\Builder): \Illuminate\Database\Query\Builder  $scope
     * @return list<AnalyticsEventRow>
     */
    private static function eventRows(callable $scope, DateTimeImmutable $from): array
    {
        $rows = $scope(DB::connection('analytics')->table('analytics_events'))
            ->where('occurred_at', '>=', $from->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'))
            // occurred_at alone ties within the same second; id — a ULID —
            // breaks the tie in the same monotonic order it was minted.
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->get();

        return array_values($rows->map(self::toEventRow(...))->all());
    }

    private static function toEventRow(stdClass $row): AnalyticsEventRow
    {
        /** @var string $name */
        $name = $row->name;
        /** @var string $occurredAt */
        $occurredAt = $row->occurred_at;
        /** @var string|null $subjectType */
        $subjectType = $row->subject_type;
        /** @var string|null $subjectId */
        $subjectId = $row->subject_id;
        /** @var string|null $actorId */
        $actorId = $row->actor_id;
        /** @var string|null $ip */
        $ip = $row->ip;
        /** @var string|null $sessionId */
        $sessionId = $row->session_id;
        /** @var string $data */
        $data = $row->data;
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($data, true);
        $requestId = $decoded['request_id'] ?? null;

        return new AnalyticsEventRow(
            $name,
            new DateTimeImmutable($occurredAt, new DateTimeZone('UTC')),
            $subjectType,
            $subjectId,
            $actorId,
            $ip,
            $sessionId,
            is_string($requestId) ? $requestId : null,
        );
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
