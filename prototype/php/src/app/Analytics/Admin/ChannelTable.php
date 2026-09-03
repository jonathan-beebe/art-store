<?php

declare(strict_types=1);

namespace App\Analytics\Admin;

use App\Analytics\RowChannel;
use App\Domain\Analytics\AnalyticsEventName;
use App\Domain\Analytics\AnalyticsRange;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Visitors, listing views, cart adds, orders placed, and orders paid, one
 * row per channel, for a range and the range before it. Every number here
 * reads through `analytics_visits` and `analytics_events`, both in the one
 * analytics SQLite file, so a channel a session belongs to is derived once
 * per raw attribution tuple SQL groups by and rows whose derived key
 * matches are folded into one — two raw tuples can derive the same
 * channel (`twitter.com` and `x.com` both read `social:x/twitter`), which
 * is why the fold happens in PHP after the SQL grouping.
 */
final class ChannelTable
{
    /** `analytics_events.name` values the report counts, and the metric on
     * {@see ChannelRow} each accumulates into. */
    private const array EVENT_METRICS = [
        AnalyticsEventName::ListingView->value => 'views',
        AnalyticsEventName::ListingCartAdd->value => 'cartAdds',
        AnalyticsEventName::OrderPlace->value => 'ordersPlaced',
        AnalyticsEventName::OrderPay->value => 'ordersPaid',
    ];

    /**
     * @return list<ChannelRow>
     */
    public static function forRange(AnalyticsRange $range): array
    {
        $previousRange = $range->previous();
        $windowStart = SqlInstant::format($previousRange->start);
        $windowEnd = SqlInstant::format($range->end);
        $currentStart = SqlInstant::format($range->start);

        /** @var array<string, array{label: string, visitors: array{current: int, previous: int}, views: array{current: int, previous: int}, cartAdds: array{current: int, previous: int}, ordersPlaced: array{current: int, previous: int}, ordersPaid: array{current: int, previous: int}}> $channels */
        $channels = [];

        foreach (self::visitorGroups($windowStart, $windowEnd, $currentStart) as $group) {
            $channel = RowChannel::of($group);
            $channels[$channel->key] ??= self::emptyBucket($channel->label);
            $channels[$channel->key]['visitors'] = self::addMetric($channels[$channel->key]['visitors'], $group);
        }

        foreach (self::eventGroups($windowStart, $windowEnd, $currentStart) as $group) {
            $channel = RowChannel::of($group);
            $channels[$channel->key] ??= self::emptyBucket($channel->label);

            /** @var string $name */
            $name = $group->name;
            $metric = self::EVENT_METRICS[$name];
            $channels[$channel->key][$metric] = self::addMetric($channels[$channel->key][$metric], $group);
        }

        $rows = [];
        foreach ($channels as $channelKey => $channel) {
            $rows[] = self::toChannelRow($channelKey, $channel);
        }

        usort($rows, fn (ChannelRow $a, ChannelRow $b): int => $b->visitors->current <=> $a->visitors->current);

        return $rows;
    }

    /**
     * @return array{label: string, visitors: array{current: int, previous: int}, views: array{current: int, previous: int}, cartAdds: array{current: int, previous: int}, ordersPlaced: array{current: int, previous: int}, ordersPaid: array{current: int, previous: int}}
     */
    private static function emptyBucket(string $label): array
    {
        $zero = ['current' => 0, 'previous' => 0];

        return [
            'label' => $label,
            'visitors' => $zero,
            'views' => $zero,
            'cartAdds' => $zero,
            'ordersPlaced' => $zero,
            'ordersPaid' => $zero,
        ];
    }

    /**
     * @param  array{current: int, previous: int}  $metric
     * @return array{current: int, previous: int}
     */
    private static function addMetric(array $metric, stdClass $group): array
    {
        /** @var int|string $current */
        $current = $group->current;
        /** @var int|string $previous */
        $previous = $group->previous;

        return [
            'current' => $metric['current'] + (int) $current,
            'previous' => $metric['previous'] + (int) $previous,
        ];
    }

    /**
     * @param  array{label: string, visitors: array{current: int, previous: int}, views: array{current: int, previous: int}, cartAdds: array{current: int, previous: int}, ordersPlaced: array{current: int, previous: int}, ordersPaid: array{current: int, previous: int}}  $channel
     */
    private static function toChannelRow(string $channelKey, array $channel): ChannelRow
    {
        return new ChannelRow(
            $channelKey,
            $channel['label'],
            ChannelMetric::of($channel['visitors']['current'], $channel['visitors']['previous']),
            ChannelMetric::of($channel['views']['current'], $channel['views']['previous']),
            ChannelMetric::of($channel['cartAdds']['current'], $channel['cartAdds']['previous']),
            ChannelMetric::of($channel['ordersPlaced']['current'], $channel['ordersPlaced']['previous']),
            ChannelMetric::of($channel['ordersPaid']['current'], $channel['ordersPaid']['previous']),
        );
    }

    /**
     * One row per distinct attribution tuple among visits whose
     * `first_seen_at` falls in the window, split into the current and the
     * previous range by `$currentStart` — the same current/previous split
     * shape every other admin analytics reader in this namespace uses.
     *
     * @return iterable<stdClass>
     */
    private static function visitorGroups(string $windowStart, string $windowEnd, string $currentStart): iterable
    {
        return DB::connection('analytics')->table('analytics_visits')
            ->whereBetween('first_seen_at', [$windowStart, $windowEnd])
            ->select('utm_source', 'utm_medium', 'utm_campaign', 'referrer_host')
            ->selectRaw('sum(case when first_seen_at >= ? then 1 else 0 end) as current', [$currentStart])
            ->selectRaw('sum(case when first_seen_at < ? then 1 else 0 end) as previous', [$currentStart])
            ->groupBy('utm_source', 'utm_medium', 'utm_campaign', 'referrer_host')
            ->get();
    }

    /**
     * One row per distinct attribution tuple and event name among the
     * four countable events, joined to the visit their session belongs
     * to — the one query this class joins `analytics_events` to
     * `analytics_visits` on `session_id`, both tables living in the one
     * analytics SQLite file. A two-query, PHP-side join was the
     * alternative; this reads fewer rows into PHP for a range with many
     * events, since the grouping happens in SQL.
     *
     * @return iterable<stdClass>
     */
    private static function eventGroups(string $windowStart, string $windowEnd, string $currentStart): iterable
    {
        return DB::connection('analytics')->table('analytics_events as e')
            ->join('analytics_visits as v', 'v.session_id', '=', 'e.session_id')
            ->whereIn('e.name', array_keys(self::EVENT_METRICS))
            ->whereBetween('e.occurred_at', [$windowStart, $windowEnd])
            ->select('v.utm_source', 'v.utm_medium', 'v.utm_campaign', 'v.referrer_host', 'e.name')
            ->selectRaw('sum(case when e.occurred_at >= ? then 1 else 0 end) as current', [$currentStart])
            ->selectRaw('sum(case when e.occurred_at < ? then 1 else 0 end) as previous', [$currentStart])
            ->groupBy('v.utm_source', 'v.utm_medium', 'v.utm_campaign', 'v.referrer_host', 'e.name')
            ->get();
    }
}
