<?php

declare(strict_types=1);

namespace App\Analytics\Admin;

use DateTimeImmutable;

/**
 * One visit {@see ChannelVisits::forRange()} reads back for a channel's
 * drill-in: when it started, where it landed, and the actor it belongs to
 * when the request that started it already carried one.
 */
final readonly class ChannelVisitRow
{
    public function __construct(
        public string $sessionId,
        public DateTimeImmutable $firstSeenAt,
        public string $landingPath,
        public ?string $actorId,
    ) {}
}
