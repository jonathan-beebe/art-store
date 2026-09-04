<?php

declare(strict_types=1);

namespace App\Analytics;

use DateTimeImmutable;

/**
 * One row {@see AnalyticsReport::eventsForIp()} or
 * {@see AnalyticsReport::eventsForSession()} reads back: what happened, the
 * moment it happened, what or who it happened to, and the request it came
 * from.
 */
final readonly class AnalyticsEventRow
{
    public function __construct(
        public string $name,
        public DateTimeImmutable $occurredAt,
        public ?string $subjectType,
        public ?string $subjectId,
        public ?string $actorId,
        public ?string $ip,
        public ?string $sessionId,
        public ?string $requestId,
    ) {}
}
