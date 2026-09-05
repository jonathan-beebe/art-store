<?php

declare(strict_types=1);

namespace App\Analytics\Admin;

use App\Analytics\ActorVisitRow;
use App\Analytics\AnalyticsReport;
use App\Domain\Analytics\ActorKind;
use App\Domain\Analytics\ActorVelocity;
use App\Domain\Analytics\AnalyticsEventName;
use App\Domain\Analytics\AnalyticsRange;
use App\Domain\Analytics\BarStrip;
use App\Domain\Analytics\BarStripBar;
use App\Domain\Analytics\FeedOtherKind;
use App\Domain\Analytics\FlaggedActorSummary;
use App\Domain\Support\RelativeTime;
use App\Models\Customer;
use App\Models\CustomerMerge;
use App\Models\Favorite;
use App\Models\Listing;
use App\Models\OrderItem;
use App\Models\StoreProfile;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * One listing's, one store's, or one actor's own page: the identity
 * card's facts, the range tiles, the strip, and the event feed.
 * `forListing()`, `forStore()`, and `forActor()` are the only entry points
 * and share every query and formatting helper below them — the pages
 * differ only in which column of `analytics_events` scopes their reads and
 * in the facts and tiles that column supports.
 */
final class EntityActivity
{
    private function __construct() {} // @codeCoverageIgnore

    /** The event feed never shows more than this many rows — an operator
     * chasing one actor or listing scrolls the feed, not the whole range. */
    private const int FEED_LIMIT = 100;

    /** The visits panel never shows more than this many rows — a returning
     * visitor's whole year of first-touch rows can outrun what the panel
     * needs to make its point. */
    private const int VISITS_LIMIT = 20;

    private const int STRIP_HEIGHT_PX = 72;

    public static function forListing(Listing $listing, AnalyticsRange $range, ?AnalyticsEventName $filter): EntityActivityView
    {
        $scope = fn (Builder $query): Builder => $query->where('subject_type', 'listing')->where('subject_id', $listing->id);

        $daily = self::dailyCounts($scope, $range);
        [$feed, $feedTotal] = self::feed($scope, $range, $filter, FeedOtherKind::Actor);
        $dayLabels = $range->dayLabels();

        return new EntityActivityView(
            kind: 'listing',
            id: $listing->id,
            title: $listing->title,
            facts: self::listingFacts($listing),
            flagged: false,
            flagText: '',
            tiles: self::listingTiles($listing, $range, $daily),
            stripTitle: 'By day',
            strip: self::dailyStripBars($daily, $dayLabels),
            stripFirst: AnalyticsRange::dayCaption($dayLabels[0]),
            stripLast: AnalyticsRange::dayCaption($dayLabels[count($dayLabels) - 1]),
            feed: $feed,
            feedCaption: self::feedCaption(count($feed), $feedTotal),
            visits: [],
        );
    }

    public static function forStore(StoreProfile $store, AnalyticsRange $range, ?AnalyticsEventName $filter): EntityActivityView
    {
        $scope = fn (Builder $query): Builder => $query->where('subject_type', 'store')->where('subject_id', $store->id);

        $daily = self::dailyCounts($scope, $range);
        [$feed, $feedTotal] = self::feed($scope, $range, $filter, FeedOtherKind::Actor);
        $dayLabels = $range->dayLabels();

        return new EntityActivityView(
            kind: 'store',
            id: $store->id,
            title: $store->name,
            facts: self::storeFacts($store),
            flagged: false,
            flagText: '',
            tiles: self::storeTiles($store, $range, $daily),
            stripTitle: 'By day',
            strip: self::dailyStripBars($daily, $dayLabels),
            stripFirst: AnalyticsRange::dayCaption($dayLabels[0]),
            stripLast: AnalyticsRange::dayCaption($dayLabels[count($dayLabels) - 1]),
            feed: $feed,
            feedCaption: self::feedCaption(count($feed), $feedTotal),
            visits: [],
        );
    }

    /**
     * `$now` is the one clock reading this needs — the "Last seen" tile
     * reads relative to it, the same instant {@see AnalyticsRange::of()}
     * built `$range` from.
     */
    public static function forActor(Customer $customer, AnalyticsRange $range, ?AnalyticsEventName $filter, DateTimeImmutable $now): EntityActivityView
    {
        $scope = fn (Builder $query): Builder => $query->where('actor_id', $customer->id);
        $visits = AnalyticsReport::visitsForActor($customer->id);

        $daily = self::dailyCounts($scope, $range);
        $eventsInRange = array_sum($daily);
        $peak = self::peakHour($customer->id, $range);
        $flagged = ActorVelocity::flags($peak['count'] ?? 0);
        $dayLabels = $range->dayLabels();

        $flagText = '';
        $stripTitle = 'By day';
        $strip = self::dailyStripBars($daily, $dayLabels);
        $stripFirst = AnalyticsRange::dayCaption($dayLabels[0]);
        $stripLast = AnalyticsRange::dayCaption($dayLabels[count($dayLabels) - 1]);

        if ($flagged && $peak !== null) {
            $peakDay = self::startOfDay($peak['start']);
            $details = self::peakHourDetails($customer->id, $peak['start']);

            $flagText = FlaggedActorSummary::text(
                $peak['count'],
                $peak['start'],
                $details['ip'],
                $details['distinctListings'],
                self::hadFavoriteOrCartEvent($customer->id, $range),
            );

            $stripTitle = 'By hour, '.$peakDay->format('M j');
            $strip = self::hourlyStripBars($customer->id, $peakDay);
            $stripFirst = '00:00';
            $stripLast = '23:00';
        }

        [$feed, $feedTotal] = self::feed($scope, $range, $filter, FeedOtherKind::Listing);

        $identity = ActorIdentity::of($customer);

        return new EntityActivityView(
            kind: $identity->kind->value,
            id: $customer->id,
            title: $identity->kind === ActorKind::Verified ? $identity->who : 'Anonymous visitor',
            facts: self::actorFacts($customer, $range, $visits),
            flagged: $flagged,
            flagText: $flagText,
            tiles: self::actorTiles($range, $eventsInRange, $peak, $customer->id, $now, $flagged),
            stripTitle: $stripTitle,
            strip: $strip,
            stripFirst: $stripFirst,
            stripLast: $stripLast,
            feed: $feed,
            feedCaption: self::feedCaption(count($feed), $feedTotal),
            visits: array_slice($visits, 0, self::VISITS_LIMIT),
        );
    }

    /**
     * @return list<EntityFact>
     */
    private static function listingFacts(Listing $listing): array
    {
        $listing->loadMissing('seller');

        return [
            new EntityFact('Seller', $listing->seller->displayName()),
            new EntityFact('Status', $listing->status->label()),
            new EntityFact('Published', $listing->created_at?->format('M j, Y g:ia') ?? '—'),
            new EntityFact('Price', $listing->price()->format()),
        ];
    }

    /**
     * @param  list<int>  $daily
     * @return list<EventTile>
     */
    private static function listingTiles(Listing $listing, AnalyticsRange $range, array $daily): array
    {
        $views = self::totalForSubject('listing', $listing->id, $range, AnalyticsEventName::ListingView);
        $favorites = self::totalForSubject('listing', $listing->id, $range, AnalyticsEventName::ListingFavorite);
        $cartAdds = self::totalForSubject('listing', $listing->id, $range, AnalyticsEventName::ListingCartAdd);
        $standingFavorites = Favorite::query()->where('listing_id', $listing->id)->count();
        $becameOrders = OrderItem::query()
            ->where('listing_id', $listing->id)
            ->whereBetween('created_at', [SqlInstant::format($range->start), SqlInstant::format($range->end)])
            ->count();
        $actors = self::distinctActorsForSubject('listing', $listing->id, $range);
        $peakIndex = self::peakDayIndex($daily);

        return [
            new EventTile('Views', number_format($views), $range->days.' days'),
            new EventTile('Favorites', number_format($favorites), number_format($standingFavorites).' standing today'),
            new EventTile('Cart adds', number_format($cartAdds), number_format($becameOrders).' became orders'),
            new EventTile('Distinct actors', number_format($actors['total']), number_format($actors['anonymous']).' anonymous'),
            new EventTile('Busiest day', number_format($daily[$peakIndex] ?? 0), AnalyticsRange::dayCaption($range->dayLabels()[$peakIndex])),
        ];
    }

    /**
     * @return list<EntityFact>
     */
    private static function storeFacts(StoreProfile $store): array
    {
        $store->loadMissing('seller');

        return [
            new EntityFact('Slug', $store->slug),
            new EntityFact('Seller', $store->seller->displayName()),
            new EntityFact('Visibility', $store->visibility()->label()),
        ];
    }

    /**
     * @param  list<int>  $daily
     * @return list<EventTile>
     */
    private static function storeTiles(StoreProfile $store, AnalyticsRange $range, array $daily): array
    {
        $views = self::totalForSubject('store', $store->id, $range, AnalyticsEventName::StoreView);
        $actors = self::distinctActorsForSubject('store', $store->id, $range);
        $peakIndex = self::peakDayIndex($daily);

        return [
            new EventTile('Views', number_format($views), $range->days.' days'),
            new EventTile('Distinct actors', number_format($actors['total']), number_format($actors['anonymous']).' anonymous'),
            new EventTile('Busiest day', number_format($daily[$peakIndex] ?? 0), AnalyticsRange::dayCaption($range->dayLabels()[$peakIndex])),
        ];
    }

    /**
     * @param  list<ActorVisitRow>  $visits  newest first
     * @return list<EntityFact>
     */
    private static function actorFacts(Customer $customer, AnalyticsRange $range, array $visits): array
    {
        $identity = ActorIdentity::of($customer);
        $ips = self::distinctIps($customer->id, $range);
        $firstSeen = self::firstSeenEver($customer->id);

        return [
            new EntityFact('Identity', $identity->who),
            new EntityFact('IPs', $ips === [] ? '—' : implode(', ', $ips)),
            new EntityFact('First seen', $firstSeen !== null ? $firstSeen->format('M j, Y g:ia') : '—'),
            new EntityFact('First channel', self::firstChannelLabel($visits)),
            new EntityFact('Merged from', self::mergedFrom($customer)),
        ];
    }

    /**
     * The earliest visit's channel label — `$visits` reads newest first
     * ({@see AnalyticsReport::visitsForActor()}), so the earliest is its
     * last element.
     *
     * @param  list<ActorVisitRow>  $visits  newest first
     */
    private static function firstChannelLabel(array $visits): string
    {
        if ($visits === []) {
            return '—';
        }

        return $visits[count($visits) - 1]->channel->label;
    }

    private static function mergedFrom(Customer $customer): string
    {
        $merges = $customer->mergesAsCustomer()->with('anonymousCustomer')->get();

        if ($merges->isEmpty()) {
            return '—';
        }

        return $merges
            ->map(function (CustomerMerge $merge): string {
                $anonymousId = $merge->anonymousCustomer instanceof Customer
                    ? $merge->anonymousCustomer->id
                    : $merge->anonymous_customer_id;

                return sprintf('%s (%s)', $anonymousId, $merge->created_at?->format('M j, Y') ?? '—');
            })
            ->implode(', ');
    }

    /**
     * @param  array{count: int, start: DateTimeImmutable}|null  $peak
     * @return list<EventTile>
     */
    private static function actorTiles(AnalyticsRange $range, int $eventsInRange, ?array $peak, string $actorId, DateTimeImmutable $now, bool $flagged): array
    {
        $peakCount = $peak['count'] ?? 0;
        $peakNote = $flagged && $peak !== null
            ? $peak['start']->format('M j').', '.$peak['start']->format('H:i').' UTC'
            : 'within the range';

        $lastSeenAt = self::lastSeen($actorId, $range);
        $lastSeenValue = $lastSeenAt !== null ? RelativeTime::short($lastSeenAt, $now) : '—';
        $lastSeenNote = $lastSeenAt !== null ? $lastSeenAt->format('M j, Y g:ia') : 'no events in range';

        $ips = self::distinctIps($actorId, $range);

        return [
            new EventTile('Events in range', number_format($eventsInRange), $range->days.' days'),
            new EventTile('Peak per hour', number_format($peakCount), $peakNote),
            new EventTile('Listings touched', number_format(self::listingsTouched($actorId, $range)), 'distinct subjects'),
            new EventTile('Sessions', number_format(self::sessionCount($actorId, $range)), count($ips).(count($ips) === 1 ? ' IP' : ' IPs')),
            new EventTile('Last seen', $lastSeenValue, $lastSeenNote),
        ];
    }

    /**
     * @param  callable(Builder): Builder  $scope
     * @return list<int> one count per day of the range, oldest first
     */
    private static function dailyCounts(callable $scope, AnalyticsRange $range): array
    {
        $rows = $scope(DB::connection('analytics')->table('analytics_events'))
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
     * @param  list<int>  $daily
     * @param  list<string>  $dayLabels
     * @return list<BarStripBar>
     */
    private static function dailyStripBars(array $daily, array $dayLabels): array
    {
        return BarStrip::bars($daily, $dayLabels, self::STRIP_HEIGHT_PX);
    }

    /**
     * @return list<BarStripBar>
     */
    private static function hourlyStripBars(string $actorId, DateTimeImmutable $dayStart): array
    {
        $dayEnd = $dayStart->modify('+1 day -1 second');

        $rows = DB::connection('analytics')->table('analytics_events')
            ->where('actor_id', $actorId)
            ->whereBetween('occurred_at', [SqlInstant::format($dayStart), SqlInstant::format($dayEnd)])
            ->selectRaw("strftime('%H', occurred_at) as hour")
            ->selectRaw('count(*) as tally')
            ->groupBy('hour')
            ->get();

        $byHour = array_fill(0, 24, 0);
        foreach ($rows as $row) {
            /** @var string $hour */
            $hour = $row->hour;
            /** @var int|string $tally */
            $tally = $row->tally;

            $byHour[(int) $hour] = (int) $tally;
        }

        $heights = BarStrip::heights(array_values($byHour), self::STRIP_HEIGHT_PX);

        return array_map(
            fn (int $height, int $hour): BarStripBar => new BarStripBar(
                $height,
                sprintf('%02d:00 UTC: %s', $hour, number_format($byHour[$hour])),
                $byHour[$hour] >= ActorVelocity::THRESHOLD_PER_HOUR,
            ),
            $heights,
            array_keys($heights),
        );
    }

    /**
     * The actor's busiest UTC hour in the range — the same aggregation
     * {@see ActorAggregates::forRange()} does across every actor at once,
     * scoped here to one, plus the hour's own start instant, which the
     * leaderboard never needs to read back out.
     *
     * @return array{count: int, start: DateTimeImmutable}|null
     */
    private static function peakHour(string $actorId, AnalyticsRange $range): ?array
    {
        $row = DB::connection('analytics')->table('analytics_events')
            ->where('actor_id', $actorId)
            ->whereBetween('occurred_at', [SqlInstant::format($range->start), SqlInstant::format($range->end)])
            ->selectRaw("strftime('%Y-%m-%dT%H', occurred_at) as hour")
            ->selectRaw('count(*) as tally')
            ->groupBy('hour')
            ->orderByDesc('tally')
            ->orderBy('hour')
            ->first();

        if ($row === null) {
            return null;
        }

        /** @var string $hour */
        $hour = $row->hour;
        /** @var int|string $tally */
        $tally = $row->tally;

        return [
            'count' => (int) $tally,
            'start' => new DateTimeImmutable($hour.':00:00', new DateTimeZone('UTC')),
        ];
    }

    /**
     * The peak hour's own ip and listing spread, for
     * {@see FlaggedActorSummary::text()} — a second pass over just that
     * one hour's rows, cheap since a flagged hour is the exception rather
     * than the rule.
     *
     * @return array{ip: string, distinctListings: int}
     */
    private static function peakHourDetails(string $actorId, DateTimeImmutable $hourStart): array
    {
        $hourEnd = $hourStart->modify('+1 hour -1 second');

        $rows = DB::connection('analytics')->table('analytics_events')
            ->where('actor_id', $actorId)
            ->whereBetween('occurred_at', [SqlInstant::format($hourStart), SqlInstant::format($hourEnd)])
            ->get(['ip', 'subject_id']);

        $ipCounts = [];
        $listingIds = [];

        foreach ($rows as $row) {
            /** @var string|null $ip */
            $ip = $row->ip;
            /** @var string|null $subjectId */
            $subjectId = $row->subject_id;

            if ($ip !== null) {
                $ipCounts[$ip] = ($ipCounts[$ip] ?? 0) + 1;
            }

            if ($subjectId !== null) {
                $listingIds[$subjectId] = true;
            }
        }

        arsort($ipCounts);
        $ip = array_key_first($ipCounts);

        return [
            'ip' => $ip ?? '—',
            'distinctListings' => count($listingIds),
        ];
    }

    private static function hadFavoriteOrCartEvent(string $actorId, AnalyticsRange $range): bool
    {
        return DB::connection('analytics')->table('analytics_events')
            ->where('actor_id', $actorId)
            ->whereIn('name', [AnalyticsEventName::ListingFavorite->value, AnalyticsEventName::ListingCartAdd->value])
            ->whereBetween('occurred_at', [SqlInstant::format($range->start), SqlInstant::format($range->end)])
            ->exists();
    }

    /**
     * @return list<string>
     */
    private static function distinctIps(string $actorId, AnalyticsRange $range): array
    {
        /** @var list<string> $ips */
        $ips = DB::connection('analytics')->table('analytics_events')
            ->where('actor_id', $actorId)
            ->whereNotNull('ip')
            ->whereBetween('occurred_at', [SqlInstant::format($range->start), SqlInstant::format($range->end)])
            ->distinct()
            ->pluck('ip')
            ->values()
            ->all();

        return $ips;
    }

    private static function sessionCount(string $actorId, AnalyticsRange $range): int
    {
        return DB::connection('analytics')->table('analytics_events')
            ->where('actor_id', $actorId)
            ->whereBetween('occurred_at', [SqlInstant::format($range->start), SqlInstant::format($range->end)])
            ->distinct()
            ->count('session_id');
    }

    private static function listingsTouched(string $actorId, AnalyticsRange $range): int
    {
        return DB::connection('analytics')->table('analytics_events')
            ->where('actor_id', $actorId)
            ->whereBetween('occurred_at', [SqlInstant::format($range->start), SqlInstant::format($range->end)])
            ->distinct()
            ->count('subject_id');
    }

    private static function lastSeen(string $actorId, AnalyticsRange $range): ?DateTimeImmutable
    {
        $row = DB::connection('analytics')->table('analytics_events')
            ->where('actor_id', $actorId)
            ->whereBetween('occurred_at', [SqlInstant::format($range->start), SqlInstant::format($range->end)])
            ->selectRaw('max(occurred_at) as last_seen')
            ->first();

        /** @var string|null $lastSeenAt */
        $lastSeenAt = $row?->last_seen;

        return $lastSeenAt === null ? null : new DateTimeImmutable($lastSeenAt, new DateTimeZone('UTC'));
    }

    private static function firstSeenEver(string $actorId): ?DateTimeImmutable
    {
        $row = DB::connection('analytics')->table('analytics_events')
            ->where('actor_id', $actorId)
            ->selectRaw('min(occurred_at) as first_seen')
            ->first();

        /** @var string|null $firstSeenAt */
        $firstSeenAt = $row?->first_seen;

        return $firstSeenAt === null ? null : new DateTimeImmutable($firstSeenAt, new DateTimeZone('UTC'));
    }

    private static function totalForSubject(string $subjectType, string $subjectId, AnalyticsRange $range, AnalyticsEventName $name): int
    {
        return DB::connection('analytics')->table('analytics_events')
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->where('name', $name->value)
            ->whereBetween('occurred_at', [SqlInstant::format($range->start), SqlInstant::format($range->end)])
            ->count();
    }

    /**
     * @return array{total: int, anonymous: int}
     */
    private static function distinctActorsForSubject(string $subjectType, string $subjectId, AnalyticsRange $range): array
    {
        $actorIds = DB::connection('analytics')->table('analytics_events')
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->whereNotNull('actor_id')
            ->whereBetween('occurred_at', [SqlInstant::format($range->start), SqlInstant::format($range->end)])
            ->distinct()
            ->pluck('actor_id');

        if ($actorIds->isEmpty()) {
            return ['total' => 0, 'anonymous' => 0];
        }

        $verified = Customer::query()->whereIn('id', $actorIds)->get()
            ->filter(fn (Customer $customer): bool => ActorIdentity::of($customer)->kind === ActorKind::Verified)
            ->count();

        return ['total' => $actorIds->count(), 'anonymous' => $actorIds->count() - $verified];
    }

    /**
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

    /**
     * @param  callable(Builder): Builder  $scope
     * @return array{0: list<EntityFeedRow>, 1: int}
     */
    private static function feed(callable $scope, AnalyticsRange $range, ?AnalyticsEventName $filter, FeedOtherKind $otherKind): array
    {
        $base = fn () => $scope(DB::connection('analytics')->table('analytics_events'))
            ->whereBetween('occurred_at', [SqlInstant::format($range->start), SqlInstant::format($range->end)]);

        $total = $base()->count();

        $query = $base();
        if ($filter !== null) {
            $query->where('name', $filter->value);
        }

        $rows = $query->orderByDesc('occurred_at')->orderByDesc('id')->limit(self::FEED_LIMIT)->get();

        $feedRows = $otherKind->isListing()
            ? self::feedRowsForActorPage($rows)
            : self::feedRowsWithActorOther($rows);

        return [$feedRows, $total];
    }

    /**
     * The listing entity page's feed: every row's other party is the actor
     * behind it.
     *
     * @param  Collection<int, stdClass>  $rows
     * @return list<EntityFeedRow>
     */
    private static function feedRowsWithActorOther(Collection $rows): array
    {
        /** @var list<string> $actorIds */
        $actorIds = $rows->pluck('actor_id')->filter()->unique()->values()->all();
        $customers = Customer::query()->whereIn('id', $actorIds)->get()->keyBy('id');

        $mapped = $rows->map(function (stdClass $row) use ($customers): EntityFeedRow {
            /** @var string $eventName */
            $eventName = $row->name;
            $name = AnalyticsEventName::from($eventName);
            /** @var string|null $actorId */
            $actorId = $row->actor_id;
            $customer = $actorId !== null ? $customers->get($actorId) : null;

            $otherLabel = $customer instanceof Customer && ActorIdentity::of($customer)->kind === ActorKind::Verified
                ? ActorIdentity::of($customer)->who
                : 'Anonymous visitor';

            return self::feedRow($row, $name, $otherLabel, $actorId ?? '', FeedOtherKind::Actor, true, []);
        })->all();

        return array_values($mapped);
    }

    /**
     * The actor entity page's feed: every row's other party is whatever
     * the event's own subject names. A listing view, favorite, or cart
     * add names the listing it happened to, which may since have been
     * deleted; a store view names the store, which may likewise have been
     * deleted. A checkout, order placement, order payment, or order
     * cancellation has no listing subject — its subject is a cart or an
     * order — so it names that instead, with the listings it spans read
     * back out of `data.listing_ids` ({@see listingIdsForFeedRow()}). One
     * `Listing::whereIn()` covers every row's listing ids at once, whether
     * they came from `subject_id` or from that JSON array; one
     * `StoreProfile::whereIn()` covers every row's store id the same way.
     *
     * @param  Collection<int, stdClass>  $rows
     * @return list<EntityFeedRow>
     */
    private static function feedRowsForActorPage(Collection $rows): array
    {
        /** @var list<string> $listingIds */
        $listingIds = $rows->flatMap(self::listingIdsForFeedRow(...))->unique()->values()->all();
        $listings = self::modelsById(Listing::class, $listingIds);
        $stores = self::modelsById(StoreProfile::class, self::storeIdsForFeedRows($rows));

        $mapped = $rows->map(function (stdClass $row) use ($listings, $stores): EntityFeedRow {
            /** @var string $eventName */
            $eventName = $row->name;
            $name = AnalyticsEventName::from($eventName);
            /** @var string|null $subjectType */
            $subjectType = $row->subject_type;

            return match ($subjectType) {
                'order' => self::feedRowForOrder($row, $name, $listings),
                'cart' => self::feedRowForCart($row, $name, $listings),
                'store' => self::feedRowForStore($row, $name, $stores),
                'help_article' => self::feedRowForHelpArticle($row, $name),
                default => self::feedRowForListing($row, $name, $listings),
            };
        })->all();

        return array_values($mapped);
    }

    /**
     * The store ids one page of feed rows names — the ids
     * {@see modelsById()} needs for {@see feedRowForStore()}.
     *
     * @param  Collection<int, stdClass>  $rows
     * @return list<string>
     */
    private static function storeIdsForFeedRows(Collection $rows): array
    {
        /** @var list<string> $storeIds */
        $storeIds = $rows
            ->filter(fn (stdClass $row): bool => $row->subject_type === 'store')
            ->pluck('subject_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $storeIds;
    }

    /**
     * Every row of `$modelClass` named by `$ids`, keyed by id — empty
     * without issuing a query when `$ids` is empty, so a feed that never
     * names this model pays nothing here.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<TModel>  $modelClass
     * @param  list<string>  $ids
     * @return Collection<string, TModel>
     */
    private static function modelsById(string $modelClass, array $ids): Collection
    {
        if ($ids === []) {
            /** @var Collection<string, TModel> $empty */
            $empty = collect();

            return $empty;
        }

        /** @var Collection<string, TModel> $found */
        $found = $modelClass::query()->whereIn('id', $ids)->get()->keyBy('id');

        return $found;
    }

    /**
     * @param  Collection<string, Listing>  $listings
     */
    private static function feedRowForListing(stdClass $row, AnalyticsEventName $name, Collection $listings): EntityFeedRow
    {
        /** @var string|null $subjectId */
        $subjectId = $row->subject_id;
        $listing = $subjectId !== null ? $listings->get($subjectId) : null;

        $otherLabel = $listing instanceof Listing ? $listing->title : 'listing no longer exists';

        return self::feedRow($row, $name, $otherLabel, $subjectId ?? '', FeedOtherKind::Listing, $listing instanceof Listing, []);
    }

    /**
     * @param  Collection<string, Listing>  $listings
     */
    private static function feedRowForOrder(stdClass $row, AnalyticsEventName $name, Collection $listings): EntityFeedRow
    {
        /** @var string $orderId */
        $orderId = $row->subject_id ?? '';

        return self::feedRow($row, $name, "order {$orderId}", $orderId, FeedOtherKind::Order, true, self::listingTitles($row, $listings));
    }

    /**
     * A cart carries no page of its own to link to, unlike an order or a
     * listing — its row names it and lists what it held, unlinked.
     *
     * @param  Collection<string, Listing>  $listings
     */
    private static function feedRowForCart(stdClass $row, AnalyticsEventName $name, Collection $listings): EntityFeedRow
    {
        /** @var string $cartId */
        $cartId = $row->subject_id ?? '';

        return self::feedRow($row, $name, "cart {$cartId}", $cartId, FeedOtherKind::Cart, false, self::listingTitles($row, $listings));
    }

    /**
     * @param  Collection<string, StoreProfile>  $stores
     */
    private static function feedRowForStore(stdClass $row, AnalyticsEventName $name, Collection $stores): EntityFeedRow
    {
        /** @var string|null $subjectId */
        $subjectId = $row->subject_id;
        $store = $subjectId !== null ? $stores->get($subjectId) : null;

        $otherLabel = $store instanceof StoreProfile ? $store->name : 'store no longer exists';

        return self::feedRow($row, $name, $otherLabel, $subjectId ?? '', FeedOtherKind::Store, $store instanceof StoreProfile, []);
    }

    /**
     * A help article carries no admin page of its own to link to, the same
     * as a cart — its row names the article by slug, unlinked.
     */
    private static function feedRowForHelpArticle(stdClass $row, AnalyticsEventName $name): EntityFeedRow
    {
        /** @var string $slug */
        $slug = $row->subject_id ?? '';

        return self::feedRow($row, $name, "article {$slug}", $slug, FeedOtherKind::HelpArticle, false, []);
    }

    /**
     * The listing ids one feed row needs {@see feedRowsForActorPage()} to
     * have already fetched: a listing subject's own id, or an order or
     * cart subject's `data.listing_ids`.
     *
     * @return list<string>
     */
    private static function listingIdsForFeedRow(stdClass $row): array
    {
        /** @var string|null $subjectType */
        $subjectType = $row->subject_type;

        if ($subjectType === 'order' || $subjectType === 'cart') {
            return self::dataListingIds($row);
        }

        if ($subjectType === 'store' || $subjectType === 'help_article') {
            return [];
        }

        /** @var string|null $subjectId */
        $subjectId = $row->subject_id;

        return $subjectId === null ? [] : [$subjectId];
    }

    /**
     * @param  Collection<string, Listing>  $listings
     * @return list<string>
     */
    private static function listingTitles(stdClass $row, Collection $listings): array
    {
        $titles = [];

        foreach (self::dataListingIds($row) as $listingId) {
            $listing = $listings->get($listingId);

            if ($listing instanceof Listing) {
                $titles[] = $listing->title;
            }
        }

        return $titles;
    }

    /**
     * @return list<string>
     */
    private static function dataListingIds(stdClass $row): array
    {
        /** @var string $data */
        $data = $row->data;
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($data, true);
        $listingIds = $decoded['listing_ids'] ?? [];

        return is_array($listingIds) ? array_values(array_filter($listingIds, is_string(...))) : [];
    }

    /**
     * @param  list<string>  $listingTitles
     */
    private static function feedRow(stdClass $row, AnalyticsEventName $name, string $otherLabel, string $otherId, FeedOtherKind $otherKind, bool $otherExists, array $listingTitles): EntityFeedRow
    {
        /** @var string $occurredAt */
        $occurredAt = $row->occurred_at;
        /** @var string|null $ip */
        $ip = $row->ip;
        /** @var string|null $sessionId */
        $sessionId = $row->session_id;

        return new EntityFeedRow(
            $name->value,
            $name->iconPath(),
            $name->verb(),
            $otherLabel,
            $otherId,
            $otherKind,
            $otherExists,
            $listingTitles,
            $ip,
            $sessionId,
            self::requestId($row),
            new DateTimeImmutable($occurredAt, new DateTimeZone('UTC')),
        );
    }

    private static function requestId(stdClass $row): ?string
    {
        /** @var string $data */
        $data = $row->data;
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($data, true);
        $requestId = $decoded['request_id'] ?? null;

        return is_string($requestId) ? $requestId : null;
    }

    private static function feedCaption(int $shown, int $total): string
    {
        return $shown.' of '.number_format($total).' shown, newest first';
    }

    private static function startOfDay(DateTimeImmutable $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment->format('Y-m-d').' 00:00:00', new DateTimeZone('UTC'));
    }
}
