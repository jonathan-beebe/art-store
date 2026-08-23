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
 * every layout already reads and yields a frame only when the number moved.
 * Polls rather than pushes, because this deployable has no queue, no
 * broadcaster, and no bus a write could publish to — one `count` query per
 * tick, for as long as one stream stays open.
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
        $lastSent = null;

        while (now()->lt($deadline)) {
            $count = Message::query()->unreadInInboxOf($actor)->count();

            if ($count !== $lastSent) {
                yield new StreamedEvent('unread', $count);
                $lastSent = $count;
            }

            Sleep::for(self::TICK_SECONDS)->seconds();
        }
    }
}
