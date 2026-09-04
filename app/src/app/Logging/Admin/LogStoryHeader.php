<?php

declare(strict_types=1);

namespace App\Logging\Admin;

/**
 * The story view's header facts, read off its (possibly capped) lines: the
 * span, the root request's method/path/status/duration, and the
 * session/actor/txn the first line that carries them names. The grouped
 * list row reuses this over one group's own lines for the same facts,
 * rather than a second read-model.
 */
final readonly class LogStoryHeader
{
    public function __construct(
        public ?string $firstTs,
        public ?string $lastTs,
        public ?int $durationMs,
        public ?string $sessionId,
        public ?string $actorType,
        public ?string $actorId,
        public ?string $txnId,
        public ?string $method,
        public ?string $path,
        public ?int $status,
    ) {}

    public static function empty(): self
    {
        return new self(null, null, null, null, null, null, null, null, null, null);
    }

    /**
     * @param  list<LogRow>  $lines  in `ts asc, id asc` order
     */
    public static function of(array $lines): self
    {
        if ($lines === []) {
            return self::empty();
        }

        $withSession = self::firstWhere($lines, fn (LogRow $line): bool => $line->sessionId !== null);
        $withActor = self::firstWhere($lines, fn (LogRow $line): bool => $line->actorId !== null);
        $withTxn = self::firstWhere($lines, fn (LogRow $line): bool => $line->txnId !== null);
        $rootOpen = self::firstWhere($lines, fn (LogRow $line): bool => $line->event === 'http.request' && $line->phase === 'will');
        // The root pair's close: `event='http.request'` and `phase` `did` or
        // `failed` — every request story closes exactly once, however the
        // connection ends (docs/spec.md §2.2).
        $rootClose = self::firstWhere(
            $lines,
            fn (LogRow $line): bool => $line->event === 'http.request'
                && ($line->phase === 'did' || $line->phase === 'failed'),
        );
        $openedData = LogRequestData::decode($rootOpen?->data);
        $closedData = LogRequestData::decode($rootClose?->data);

        return new self(
            firstTs: $lines[0]->ts,
            lastTs: $lines[count($lines) - 1]->ts,
            durationMs: $rootClose?->durationMs,
            sessionId: $withSession?->sessionId,
            actorType: $withActor?->actorType,
            actorId: $withActor?->actorId,
            txnId: $withTxn?->txnId,
            method: LogRequestData::stringField($openedData, 'method'),
            path: LogRequestData::stringField($openedData, 'path'),
            status: LogRequestData::intField($closedData, 'status'),
        );
    }

    /**
     * @param  list<LogRow>  $lines
     * @param  callable(LogRow): bool  $predicate
     */
    private static function firstWhere(array $lines, callable $predicate): ?LogRow
    {
        foreach ($lines as $line) {
            if ($predicate($line)) {
                return $line;
            }
        }

        return null;
    }
}
