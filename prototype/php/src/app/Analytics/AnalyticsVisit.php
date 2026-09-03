<?php

declare(strict_types=1);

namespace App\Analytics;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\Request;

/**
 * The first-touch facts of one browser session: where it landed, what
 * referred it, and what campaign it named — everything
 * {@see Channel::derive()} needs to say which origin a session's later
 * events belong to. {@see Analytics::recordVisit()} buffers it the same
 * way {@see Analytics::recordEvent()} buffers an event; flushing writes it
 * `INSERT OR IGNORE` on `session_id`, so only the first request of a
 * session's rows ever lands.
 */
final readonly class AnalyticsVisit
{
    private const int VALUE_LENGTH = 255;

    public function __construct(
        public string $sessionId,
        public DateTimeImmutable $firstSeenAt,
        public string $landingPath,
        public ?string $referrerHost,
        public ?string $utmSource,
        public ?string $utmMedium,
        public ?string $utmCampaign,
        public ?string $utmContent,
        public ?string $utmTerm,
        public ?string $actorId,
    ) {}

    /**
     * A visit built from known facts rather than read off a request — a
     * console command driving a scripted visitor, for instance, where the
     * session id, landing path, and channel all come from the script
     * instead. `$utm` carries whichever of `source`, `medium`, `campaign`,
     * `content`, and `term` the visit names; a key it omits, or names null,
     * leaves that field null. Every capped value goes through the same
     * 255-character limit {@see fromRequest()} applies.
     *
     * @param  array{source?: ?string, medium?: ?string, campaign?: ?string, content?: ?string, term?: ?string}  $utm
     */
    public static function of(
        string $sessionId,
        DateTimeImmutable $firstSeenAt,
        string $landingPath,
        ?string $referrerHost,
        array $utm,
        ?string $actorId,
    ): self {
        return new self(
            $sessionId,
            $firstSeenAt,
            $landingPath,
            $referrerHost === null ? null : self::cap($referrerHost),
            self::cappedUtm($utm, 'source'),
            self::cappedUtm($utm, 'medium'),
            self::cappedUtm($utm, 'campaign'),
            self::cappedUtm($utm, 'content'),
            self::cappedUtm($utm, 'term'),
            $actorId,
        );
    }

    /**
     * `$facts->sessionId` is the row's key — null when the request carries
     * no session at all, which never happens on a real HTTP request since
     * `RequestFacts` falls back to the cookie `NameRequestVisitor` just
     * queued, but does happen for the synthetic request a console command
     * binds. `$actorId` is the customer already resolved for the request,
     * when the route resolves one — null everywhere else. `Referer` is
     * read as a foreign host only: absent on a direct visit and on
     * same-site navigation, so both leave `referrerHost` null.
     */
    public static function fromRequest(Request $request, RequestFacts $facts, ?string $actorId, DateTimeImmutable $at): ?self
    {
        if ($facts->sessionId === null) {
            return null;
        }

        return new self(
            $facts->sessionId,
            $at,
            $request->getPathInfo(),
            self::foreignReferrerHost($request),
            self::utmParam($request, 'utm_source'),
            self::utmParam($request, 'utm_medium'),
            self::utmParam($request, 'utm_campaign'),
            self::utmParam($request, 'utm_content'),
            self::utmParam($request, 'utm_term'),
            $actorId,
        );
    }

    /**
     * The row {@see Analytics::flush()} inserts into `analytics_visits`.
     * `first_seen_at` is stamped in UTC using the same format every
     * timestamp column on this connection already stores.
     *
     * @return array{session_id: string, first_seen_at: string, landing_path: string, referrer_host: string|null, utm_source: string|null, utm_medium: string|null, utm_campaign: string|null, utm_content: string|null, utm_term: string|null, actor_id: string|null}
     */
    public function columns(): array
    {
        return [
            'session_id' => $this->sessionId,
            'first_seen_at' => $this->firstSeenAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'landing_path' => $this->landingPath,
            'referrer_host' => $this->referrerHost,
            'utm_source' => $this->utmSource,
            'utm_medium' => $this->utmMedium,
            'utm_campaign' => $this->utmCampaign,
            'utm_content' => $this->utmContent,
            'utm_term' => $this->utmTerm,
            'actor_id' => $this->actorId,
        ];
    }

    private static function foreignReferrerHost(Request $request): ?string
    {
        $referer = $request->headers->get('Referer');

        if (! is_string($referer) || $referer === '') {
            return null;
        }

        $host = parse_url($referer, PHP_URL_HOST);

        if (! is_string($host) || $host === '' || strcasecmp($host, $request->getHost()) === 0) {
            return null;
        }

        return self::cap(strtolower($host));
    }

    private static function utmParam(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        return is_string($value) && $value !== '' ? self::cap($value) : null;
    }

    private static function cap(string $value): string
    {
        return mb_substr($value, 0, self::VALUE_LENGTH);
    }

    /**
     * @param  array{source?: ?string, medium?: ?string, campaign?: ?string, content?: ?string, term?: ?string}  $utm
     */
    private static function cappedUtm(array $utm, string $key): ?string
    {
        $value = $utm[$key] ?? null;

        return $value === null ? null : self::cap($value);
    }
}
