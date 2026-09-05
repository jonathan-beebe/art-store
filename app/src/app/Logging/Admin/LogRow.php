<?php

declare(strict_types=1);

namespace App\Logging\Admin;

/**
 * One stored line as the viewer shows it: every mirrored `log_lines` column
 * except `raw`, which the list and story views never read.
 */
final readonly class LogRow
{
    public function __construct(
        public int $id,
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
    ) {}

    /**
     * @param  array<string, mixed>  $row  one `PDO::FETCH_ASSOC` row over
     *                                     `LogRowQuery::ROW_COLUMNS`
     */
    public static function fromDatabase(array $row): self
    {
        $id = $row['id'] ?? null;
        $ts = $row['ts'] ?? null;
        $durationMs = $row['duration_ms'] ?? null;

        return new self(
            id: is_numeric($id) ? (int) $id : 0,
            ts: is_string($ts) ? $ts : '',
            level: self::stringOrNull($row['level']),
            event: self::stringOrNull($row['event']),
            phase: self::stringOrNull($row['phase']),
            msg: self::stringOrNull($row['msg']),
            requestId: self::stringOrNull($row['request_id']),
            sessionId: self::stringOrNull($row['session_id']),
            actorType: self::stringOrNull($row['actor_type']),
            actorId: self::stringOrNull($row['actor_id']),
            txnId: self::stringOrNull($row['txn_id']),
            durationMs: is_numeric($durationMs) ? (int) $durationMs : null,
            data: self::stringOrNull($row['data']),
            error: self::stringOrNull($row['error']),
        );
    }

    /** The key a request-grouped view keys this line's request by, when it
     * carries none of its own: an orphan line groups alone, not by
     * `txn_id`. */
    public function groupKey(): string
    {
        return $this->requestId ?? "line:{$this->id}";
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
