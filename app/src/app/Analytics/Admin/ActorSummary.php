<?php

declare(strict_types=1);

namespace App\Analytics\Admin;

use DateTimeImmutable;

/**
 * One actor's row on the admin analytics leaderboard or its all-actors
 * page: what it did in the range and who it is, from
 * {@see ActorAggregates::forRange()}. `$firstSeenAt` is the actor's
 * earliest event ever, not bounded by the range the rest of the row reads
 * over — the all-actors page's "First seen" column.
 */
final readonly class ActorSummary
{
    /**
     * @param  list<string>  $ips
     */
    public function __construct(
        public string $id,
        public string $kind,
        public string $who,
        public array $ips,
        public int $events,
        public int $peakPerHour,
        public int $subjects,
        public DateTimeImmutable $firstSeenAt,
        public DateTimeImmutable $lastSeenAt,
        public bool $flagged,
    ) {}
}
