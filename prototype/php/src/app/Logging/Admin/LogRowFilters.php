<?php

declare(strict_types=1);

namespace App\Logging\Admin;

use App\Logging\LogDomain;

/**
 * The `/admin/logs` filter set, already validated by
 * `App\Http\Requests\Admin\LogsQueryRequest` — every query `LogRowQuery` runs
 * builds its `WHERE` clause from one of these, so the count, the page, the
 * level tallies, and the grouped view all agree on what a filter means.
 */
final readonly class LogRowFilters
{
    /** The dotted identifier path the any-attribute filter accepts; the
     * route answers 400 for anything else. */
    public const string ATTRIBUTE_KEY_PATTERN = '/^[A-Za-z0-9_]+(?:\.[A-Za-z0-9_]+){0,3}$/';

    public function __construct(
        public ?LogDomain $domain = null,
        public ?string $level = null,
        public ?string $phase = null,
        public ?string $event = null,
        public ?string $requestId = null,
        public ?string $txnId = null,
        public ?string $sessionId = null,
        public ?string $actorId = null,
        public ?string $msg = null,
        public ?string $from = null,
        public ?string $to = null,
        public ?string $key = null,
        public ?string $value = null,
        /** Health-check request pairs are excluded when this is `true` — the
         * viewer's default. `false` includes them. */
        public bool $hideHealth = true,
    ) {}

    /** The four stat tiles tally the current filters minus `level` itself,
     * so each tile can double as the level filter's fast path. */
    public function withoutLevel(): self
    {
        return new self(
            domain: $this->domain,
            level: null,
            phase: $this->phase,
            event: $this->event,
            requestId: $this->requestId,
            txnId: $this->txnId,
            sessionId: $this->sessionId,
            actorId: $this->actorId,
            msg: $this->msg,
            from: $this->from,
            to: $this->to,
            key: $this->key,
            value: $this->value,
            hideHealth: $this->hideHealth,
        );
    }
}
