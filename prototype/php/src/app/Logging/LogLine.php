<?php

declare(strict_types=1);

namespace App\Logging;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use stdClass;

/**
 * One row of `log_lines`, parsed from a single stdout line. docs/logging.md
 * §"Table": the eleven docs/alignment.md §2.1 fields map to same-named
 * columns, `data`/`error` are re-serialized as JSON text, and `raw` is the
 * whole line, capped. A line that does not parse as a JSON object stores
 * `raw` plus a receive-time `ts` with every other column null — the store
 * mirrors what was emitted, it does not validate it.
 */
final readonly class LogLine
{
    private const int RAW_CAP_BYTES = 64 * 1024;

    /**
     * ISO-8601 UTC with milliseconds, matching the shape
     * `App\Logging\StoryFormatter` writes `ts` in, so a receive-time
     * fallback sorts correctly beside parsed ones.
     */
    private const string TIMESTAMP = 'Y-m-d\TH:i:s.v\Z';

    private function __construct(
        public string $ts,
        public ?string $level,
        public ?string $event,
        public ?string $phase,
        public ?string $msg,
        public ?string $requestId,
        public ?string $sessionId,
        public ?string $actorType,
        public ?string $actorId,
        public ?string $txnId,
        public ?int $durationMs,
        public ?string $data,
        public ?string $error,
        public string $raw,
    ) {}

    public static function parse(string $line): self
    {
        $raw = self::cappedRaw($line);
        $fields = self::decode($line);

        if ($fields === null) {
            return new self(
                ts: self::now(),
                level: null,
                event: null,
                phase: null,
                msg: null,
                requestId: null,
                sessionId: null,
                actorType: null,
                actorId: null,
                txnId: null,
                durationMs: null,
                data: null,
                error: null,
                raw: $raw,
            );
        }

        return new self(
            ts: self::stringOrNull($fields['ts'] ?? null) ?? self::now(),
            level: self::stringOrNull($fields['level'] ?? null),
            event: self::stringOrNull($fields['event'] ?? null),
            phase: self::stringOrNull($fields['phase'] ?? null),
            msg: self::stringOrNull($fields['msg'] ?? null),
            requestId: self::stringOrNull($fields['request_id'] ?? null),
            sessionId: self::stringOrNull($fields['session_id'] ?? null),
            actorType: self::stringOrNull($fields['actor_type'] ?? null),
            actorId: self::stringOrNull($fields['actor_id'] ?? null),
            txnId: self::stringOrNull($fields['txn_id'] ?? null),
            durationMs: self::intOrNull($fields['duration_ms'] ?? null),
            data: self::jsonTextOrNull($fields, 'data'),
            error: self::jsonTextOrNull($fields, 'error'),
            raw: $raw,
        );
    }

    /**
     * The bound values one row contributes to the multi-row INSERT, in
     * `log_lines`' column order.
     *
     * @return list<string|int|null>
     */
    public function columns(): array
    {
        return [
            $this->ts,
            $this->level,
            $this->event,
            $this->phase,
            $this->msg,
            $this->requestId,
            $this->sessionId,
            $this->actorType,
            $this->actorId,
            $this->txnId,
            $this->durationMs,
            $this->data,
            $this->error,
            $this->raw,
        ];
    }

    /**
     * A JSON object, decoded with `stdClass` rather than an associative array
     * so an empty object (`{}`) and a JSON array are told apart the way
     * `json_decode` cannot with `$associative = true` alone.
     *
     * @return array<string, mixed>|null
     */
    private static function decode(string $line): ?array
    {
        try {
            $decoded = json_decode($line, associative: false, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! $decoded instanceof stdClass) {
            return null;
        }

        /** @var array<string, mixed> $fields */
        $fields = get_object_vars($decoded);

        return $fields;
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function intOrNull(mixed $value): ?int
    {
        return match (true) {
            is_int($value) => $value,
            is_float($value) => (int) $value,
            default => null,
        };
    }

    /**
     * `data`/`error` are stored re-serialized, `null` when the field was
     * absent from the line — distinct from a field present and JSON `null`,
     * which stores the text `"null"`.
     *
     * @param  array<string, mixed>  $fields
     */
    private static function jsonTextOrNull(array $fields, string $key): ?string
    {
        if (! array_key_exists($key, $fields)) {
            return null;
        }

        $encoded = json_encode($fields[$key]);

        return $encoded === false ? null : $encoded;
    }

    private static function cappedRaw(string $line): string
    {
        return strlen($line) <= self::RAW_CAP_BYTES ? $line : substr($line, 0, self::RAW_CAP_BYTES);
    }

    private static function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(self::TIMESTAMP);
    }
}
