<?php

declare(strict_types=1);

namespace App\Analytics;

use App\Domain\Analytics\AnalyticsEventName;
use App\Identifiers\IdMint;
use DateTimeImmutable;
use DateTimeZone;

/**
 * One occurrence {@see Analytics::recordEvent()} buffers: what happened,
 * the moment it happened, and what or who it happened to. `subjectType`
 * and `subjectId` name the row an event is about (a listing, a cart, or an
 * order); `actorId` names who caused it, when the store knows. `ip` and
 * `sessionId` name the request it came from, when there was one — see
 * {@see withRequestFacts()}.
 */
final readonly class AnalyticsEvent
{
    private const string ID_PREFIX = 'aev';

    private const string SUBJECT_LISTING = 'listing';

    private const string SUBJECT_CART = 'cart';

    private const string SUBJECT_ORDER = 'order';

    private const string SUBJECT_STORE = 'store';

    private const string SUBJECT_HELP_ARTICLE = 'help_article';

    /**
     * @param  array<string, scalar|list<string>|null>  $data
     */
    public function __construct(
        public AnalyticsEventName $name,
        public DateTimeImmutable $occurredAt,
        public ?string $subjectType,
        public ?string $subjectId,
        public ?string $actorId,
        public ?string $dedupeKey,
        public array $data = [],
        public ?string $ip = null,
        public ?string $sessionId = null,
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
     * A buyer opening a store's public page, attributed to the customer who
     * opened it — or to nobody, for an anonymous visitor.
     */
    public static function forStore(
        AnalyticsEventName $name,
        string $storeProfileId,
        ?string $customerId,
        DateTimeImmutable $at,
        ?string $dedupeKey = null,
    ): self {
        return new self($name, $at, self::SUBJECT_STORE, $storeProfileId, $customerId, $dedupeKey);
    }

    /**
     * A seller's "Did this answer it?" click on a help article. `actorId`
     * stays null: every actor reader in `App\Analytics\Admin` resolves an
     * `actor_id` against `customers`, and a seller is never a customer.
     * `$sellerId` rides in `data` instead, where a reader that wants it
     * can read it without joining the actor vocabulary.
     */
    public static function forHelpArticle(
        AnalyticsEventName $name,
        string $articleSlug,
        string $sellerId,
        DateTimeImmutable $at,
        string $dedupeKey,
    ): self {
        return new self($name, $at, self::SUBJECT_HELP_ARTICLE, $articleSlug, null, $dedupeKey, ['seller_id' => $sellerId]);
    }

    /**
     * A step in the order lifecycle, attributed to the customer whose order
     * it is — or to nobody, for a guest order the sweep cancels before it is
     * ever claimed. `$data` carries `listing_ids`, the listings the order
     * spans, and — for {@see AnalyticsEventName::OrderPay} —
     * `total_cents`, so a revenue report reads the amount without a join
     * back to the commerce database.
     *
     * @param  array<string, scalar|list<string>|null>  $data
     */
    public static function forOrder(
        AnalyticsEventName $name,
        string $orderId,
        ?string $customerId,
        DateTimeImmutable $at,
        array $data = [],
    ): self {
        return new self($name, $at, self::SUBJECT_ORDER, $orderId, $customerId, null, $data);
    }

    /**
     * Checkout opened, before an order exists to name — the cart it was
     * opened on is the subject. `$data` carries the cart's `listing_ids`,
     * the same key {@see forOrder()} carries them under once the cart
     * becomes an order.
     *
     * @param  array<string, scalar|list<string>|null>  $data
     */
    public static function forCart(
        AnalyticsEventName $name,
        string $cartId,
        ?string $customerId,
        DateTimeImmutable $at,
        array $data = [],
    ): self {
        return new self($name, $at, self::SUBJECT_CART, $cartId, $customerId, null, $data);
    }

    /**
     * The same event, carrying the ip, session, and request id of the
     * request that produced it. `Analytics::recordEvent()` calls this once
     * per event, with whatever {@see RequestFacts::current()} found; a CLI
     * run's facts are all null and leave the event as recorded. The request
     * id folds into `data`. It is a cross-link to the log store, never
     * something a reader filters on.
     */
    public function withRequestFacts(RequestFacts $facts): self
    {
        return new self(
            $this->name,
            $this->occurredAt,
            $this->subjectType,
            $this->subjectId,
            $this->actorId,
            $this->dedupeKey,
            $facts->requestId === null ? $this->data : [...$this->data, 'request_id' => $facts->requestId],
            $facts->ip,
            $facts->sessionId,
        );
    }

    /**
     * The row {@see Analytics::flush()} inserts into `analytics_events`.
     * `occurred_at` is stamped in UTC using the format every timestamp
     * column on this connection already stores
     * ({@see \Illuminate\Database\Grammar::getDateFormat()}), so `date()`
     * grouping on the column reads it the same way it reads any other row.
     *
     * @return array{id: string, name: string, occurred_at: string, subject_type: string|null, subject_id: string|null, actor_id: string|null, ip: string|null, session_id: string|null, dedupe_key: string|null, data: string}
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
            'ip' => $this->ip,
            'session_id' => $this->sessionId,
            'dedupe_key' => $this->dedupeKey,
            'data' => json_encode($this->data, JSON_THROW_ON_ERROR),
        ];
    }
}
