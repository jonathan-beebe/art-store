<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Analytics\Admin\ActorSummary;
use App\Analytics\Admin\ChannelMetric;
use App\Analytics\Admin\ChannelRow;
use App\Analytics\Admin\EventTotal;
use App\Analytics\AnalyticsEventRow;
use App\Logging\Admin\LogRow;
use DateTimeImmutable;
use DateTimeZone;

/**
 * The JSON shape of every row a tool answers with: the admin readers'
 * value objects, flattened to snake_case keys, with a stored line's
 * `data` and `error` decoded back into objects.
 */
final class ToolRows
{
    private function __construct() {} // @codeCoverageIgnore

    public static function instant(DateTimeImmutable $moment): string
    {
        return $moment->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }

    /**
     * @return array<string, mixed>
     */
    public static function logRow(LogRow $row): array
    {
        return [
            'id' => $row->id,
            'ts' => $row->ts,
            'level' => $row->level,
            'event' => $row->event,
            'phase' => $row->phase,
            'msg' => $row->msg,
            'request_id' => $row->requestId,
            'session_id' => $row->sessionId,
            'actor_type' => $row->actorType,
            'actor_id' => $row->actorId,
            'txn_id' => $row->txnId,
            'duration_ms' => $row->durationMs,
            'data' => self::decoded($row->data),
            'error' => self::decoded($row->error),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function analyticsEvent(AnalyticsEventRow $row): array
    {
        return [
            'name' => $row->name,
            'occurred_at' => self::instant($row->occurredAt),
            'subject_type' => $row->subjectType,
            'subject_id' => $row->subjectId,
            'actor_id' => $row->actorId,
            'ip' => $row->ip,
            'session_id' => $row->sessionId,
            'request_id' => $row->requestId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function eventTotal(EventTotal $total): array
    {
        return [
            'name' => $total->name,
            'label' => $total->label,
            'current' => $total->current,
            'previous' => $total->previous,
            'change' => $total->change->text,
            'direction' => strtolower($total->change->direction->name),
            'daily' => $total->daily,
            'subjects' => $total->subjects,
            'actors' => $total->actors,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function channelRow(ChannelRow $row): array
    {
        return [
            'channel' => $row->label,
            'visitors' => self::channelMetric($row->visitors),
            'listing_views' => self::channelMetric($row->views),
            'cart_adds' => self::channelMetric($row->cartAdds),
            'orders_placed' => self::channelMetric($row->ordersPlaced),
            'orders_paid' => self::channelMetric($row->ordersPaid),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function actorSummary(ActorSummary $actor): array
    {
        return [
            'id' => $actor->id,
            'kind' => $actor->kind,
            'who' => $actor->who,
            'ips' => $actor->ips,
            'events' => $actor->events,
            'peak_per_hour' => $actor->peakPerHour,
            'subjects' => $actor->subjects,
            'first_seen_at' => self::instant($actor->firstSeenAt),
            'last_seen_at' => self::instant($actor->lastSeenAt),
            'flagged' => $actor->flagged,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function channelMetric(ChannelMetric $metric): array
    {
        return [
            'current' => $metric->current,
            'previous' => $metric->previous,
            'change' => $metric->change->text,
            'direction' => strtolower($metric->change->direction->name),
        ];
    }

    /**
     * A stored `data` or `error` column holds JSON text; a line that was
     * stored unparsed keeps whatever text it had.
     */
    private static function decoded(?string $json): mixed
    {
        if ($json === null) {
            return null;
        }

        $decoded = json_decode($json, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $json;
    }
}
