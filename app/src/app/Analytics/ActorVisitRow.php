<?php

declare(strict_types=1);

namespace App\Analytics;

use App\Domain\Analytics\Channel;
use DateTimeImmutable;

/**
 * One visit {@see AnalyticsReport::visitsForActor()} reads back: when it
 * started, where it landed, what referred it, and the {@see Channel} it
 * derives to — an actor's own page lists these to show the origin of each
 * of their visits.
 */
final readonly class ActorVisitRow
{
    public function __construct(
        public string $sessionId,
        public DateTimeImmutable $firstSeenAt,
        public string $landingPath,
        public ?string $referrerHost,
        public Channel $channel,
    ) {}
}
