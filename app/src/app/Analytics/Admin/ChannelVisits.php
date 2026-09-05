<?php

declare(strict_types=1);

namespace App\Analytics\Admin;

use App\Analytics\RowChannel;
use App\Domain\Analytics\AnalyticsRange;
use App\Domain\Analytics\Channel;
use App\Domain\Paging\Page;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * One channel's own visits for a range, paged — the drill-in
 * {@see \App\Http\Controllers\Admin\Analytics\ChannelController::show()}
 * reads. A channel key names no stored row: `forRange()` derives every
 * visit's {@see Channel} in PHP the way {@see ChannelTable} does and keeps
 * only the ones that match, so "found" means at least one visit in the
 * range derives to the given key — an empty match answers null, the
 * controller's cue to 404.
 */
final class ChannelVisits
{
    private function __construct() {} // @codeCoverageIgnore

    public static function forRange(AnalyticsRange $range, string $channelKey, int $page, int $perPage = 25): ?ChannelVisitsPage
    {
        // Every visit in the range is read into PHP before the derived key
        // filters and the page slices it — bounded by the range's own
        // visit volume, the way `ChannelTable::forRange()`'s own grouping
        // query is, and small at the app's scale.
        $rows = DB::connection('analytics')->table('analytics_visits')
            ->whereBetween('first_seen_at', [SqlInstant::format($range->start), SqlInstant::format($range->end)])
            ->select('session_id', 'first_seen_at', 'landing_path', 'actor_id', 'utm_source', 'utm_medium', 'utm_campaign', 'referrer_host')
            ->get();

        $label = null;
        $matched = [];

        foreach ($rows as $row) {
            $channel = RowChannel::of($row);

            if ($channel->key !== $channelKey) {
                continue;
            }

            $label ??= $channel->label;
            $matched[] = self::toVisitRow($row);
        }

        if ($matched === [] || $label === null) {
            return null;
        }

        usort($matched, fn (ChannelVisitRow $a, ChannelVisitRow $b): int => $b->firstSeenAt <=> $a->firstSeenAt ?: $b->sessionId <=> $a->sessionId);

        $paged = Page::of((string) $page, $perPage, count($matched));

        return new ChannelVisitsPage($label, $paged, array_slice($matched, $paged->offset, $paged->limit));
    }

    private static function toVisitRow(stdClass $row): ChannelVisitRow
    {
        /** @var string $sessionId */
        $sessionId = $row->session_id;
        /** @var string $firstSeenAt */
        $firstSeenAt = $row->first_seen_at;
        /** @var string $landingPath */
        $landingPath = $row->landing_path;
        /** @var string|null $actorId */
        $actorId = $row->actor_id;

        return new ChannelVisitRow(
            $sessionId,
            new DateTimeImmutable($firstSeenAt, new DateTimeZone('UTC')),
            $landingPath,
            $actorId,
        );
    }
}
