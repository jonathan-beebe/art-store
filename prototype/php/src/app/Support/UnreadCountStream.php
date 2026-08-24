<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Seller;
use DateTimeImmutable;
use Generator;
use Illuminate\Http\StreamedEvent;
use Illuminate\Support\Sleep;

/**
 * The live badge's producer: a generator that re-runs the same unread scope
 * every layout already reads and yields the count on every tick. Polls
 * rather than pushes, because this deployable has no queue, no broadcaster,
 * and no bus a write could publish to — one `count` query per tick, for as
 * long as one stream stays open.
 *
 * A frame per tick is what keeps a closed tab from parking a worker:
 * `eventStream` writes each frame and checks `connection_aborted()` once per
 * frame, and PHP learns a connection is gone only from a failed write, so a
 * stream that yields nothing never notices the browser left. A repeated
 * count costs the browser nothing — `live-badge.js` sets the label from
 * whatever the frame carries.
 */
final class UnreadCountStream
{
    private function __construct() {} // @codeCoverageIgnore

    /** How often the count is re-read while a stream is open. */
    public const int TICK_SECONDS = 2;

    /** How long a stream stays open before it ends and the browser reconnects. */
    public const int LIFETIME_SECONDS = 25;

    /**
     * Reads only the given actor's own inbox — nothing a client sends
     * chooses whose count this is, since the caller already resolved the
     * actor from a guard before the deadline is reached.
     *
     * @return Generator<int, StreamedEvent>
     */
    public static function forActor(Seller|Customer|Admin $actor, DateTimeImmutable $deadline): Generator
    {
        while (now()->lt($deadline)) {
            yield new StreamedEvent('unread', Message::query()->unreadInInboxOf($actor)->count());

            Sleep::for(self::TICK_SECONDS)->seconds();
        }
    }
}
