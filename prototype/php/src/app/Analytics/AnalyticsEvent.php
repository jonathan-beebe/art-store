<?php

declare(strict_types=1);

namespace App\Analytics;

use App\Domain\Analytics\AnalyticsEventName;
use App\Support\IdMint;
use DateTimeImmutable;
use DateTimeZone;

/**
 * One occurrence {@see Analytics::recordEvent()} buffers: what happened,
 * the moment it happened, and what or who it happened to. `subjectType`
 * and `subjectId` name the row an event is about (a listing, today);
 * `actorId` names who caused it, when the store knows.
 */
final readonly class AnalyticsEvent
{
    private const string ID_PREFIX = 'aev';

    private const string SUBJECT_LISTING = 'listing';

    /**
     * @param  array<string, scalar|null>  $data
     */
    public function __construct(
        public AnalyticsEventName $name,
        public DateTimeImmutable $occurredAt,
        public ?string $subjectType,
        public ?string $subjectId,
        public ?string $actorId,
        public ?string $dedupeKey,
        public array $data = [],
    ) {}

    /**
     * A listing interaction, attributed to the customer who triggered it —
     * or to nobody, for an anonymous visitor.
     */
    public static function forListing(
        AnalyticsEventName $name,
        string $listingId,
        ?string $customerId,
        DateTimeImmutable $at,
        ?string $dedupeKey = null,
    ): self {
        return new self($name, $at, self::SUBJECT_LISTING, $listingId, $customerId, $dedupeKey);
    }

    /**
     * The row {@see Analytics::flush()} inserts into `analytics_events`.
     * `occurred_at` is stamped in UTC using the format every timestamp
     * column on this connection already stores
     * ({@see \Illuminate\Database\Grammar::getDateFormat()}), so `date()`
     * grouping on the column reads it the same way it reads any other row.
     *
     * @return array{id: string, name: string, occurred_at: string, subject_type: string|null, subject_id: string|null, actor_id: string|null, dedupe_key: string|null, data: string}
     */
    public function columns(): array
    {
        return [
            'id' => IdMint::of(self::ID_PREFIX),
            'name' => $this->name->value,
            'occurred_at' => $this->occurredAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'subject_type' => $this->subjectType,
            'subject_id' => $this->subjectId,
            'actor_id' => $this->actorId,
            'dedupe_key' => $this->dedupeKey,
            'data' => json_encode($this->data, JSON_THROW_ON_ERROR),
        ];
    }
}
