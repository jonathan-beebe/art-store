<?php

declare(strict_types=1);

namespace App\Logging\Admin;

use App\Logging\LogDomain;
use App\Logging\StoryLevel;
use PDO;
use PDOStatement;

/**
 * What `/admin/logs` and the story view read out of the log store
 * (docs/logging.md § "Viewer"), over the `PDO` handle
 * `App\Logging\LogStore::$connection` exposes. Every read builds its
 * `WHERE` clause from `conditions()`, so the count, the page, the level
 * tallies, and the grouped view all agree on what a filter means.
 */
final readonly class LogRowQuery
{
    /** The story view stops here and says so; `?txn=` on the list covers
     * the rest. */
    public const int STORY_LINE_CAP = 1000;

    private const string ROW_COLUMNS = 'id, ts, level, event, phase, msg, request_id, session_id, actor_type, actor_id, txn_id, duration_ms, data, error';

    /** The orphan-group key prefix: a line with no `request_id` groups
     * alone rather than by `txn_id`. */
    private const string LINE_GROUP_PREFIX = 'line:';

    /** The orchestrator's healthcheck path — the container polls it on an
     * interval, and its lines are hidden from the default list. */
    private const string HEALTH_CHECK_PATH = '/health';

    /** The storefront's own unread-events stream sits at the unprefixed
     * root beside every other shop page, but neither it nor the health
     * probe is traffic a founder means by "shop" — excluded by name. */
    private const string SHOP_EXCLUDED_PATH = '/events';

    /** The columns the any-attribute filter short-circuits to, keyed as a
     * log line names them, so the indexes serve a key that has one. */
    private const array MIRRORED_COLUMNS = [
        'ts' => 'log_lines.ts',
        'level' => 'log_lines.level',
        'event' => 'log_lines.event',
        'phase' => 'log_lines.phase',
        'msg' => 'log_lines.msg',
        'request_id' => 'log_lines.request_id',
        'session_id' => 'log_lines.session_id',
        'actor_type' => 'log_lines.actor_type',
        'actor_id' => 'log_lines.actor_id',
        'txn_id' => 'log_lines.txn_id',
        'duration_ms' => 'log_lines.duration_ms',
    ];

    /** A JSON number and a string that looks like one both answer a
     * numeric value. */
    private const string NUMERIC_VALUE = '/^-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?$/';

    public function __construct(private PDO $connection) {}

    /** How many lines match `$filters`, independent of which page of them
     * is shown. */
    public function count(LogRowFilters $filters): int
    {
        [$where, $params] = $this->whereClause($filters);
        $statement = $this->connection->prepare("SELECT COUNT(*) FROM log_lines{$where}");
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    /** One page of matching lines, newest first — `ts desc` with the rowid
     * as the tiebreak within one millisecond.
     *
     * @return list<LogRow>
     */
    public function rows(LogRowFilters $filters, int $limit, int $offset): array
    {
        [$where, $params] = $this->whereClause($filters);
        $statement = $this->connection->prepare(
            'SELECT '.self::ROW_COLUMNS." FROM log_lines{$where} ORDER BY ts DESC, id DESC LIMIT ? OFFSET ?",
        );
        $statement->execute([...$params, $limit, $offset]);

        return $this->fetchRows($statement);
    }

    /** How many lines each level holds under the current filters minus
     * `level` itself, so the four stat tiles double as the level filter's
     * fast path. Every level answers, zero included.
     *
     * @return array<string, int>
     */
    public function levelTallies(LogRowFilters $filters): array
    {
        [$where, $params] = $this->whereClause($filters->withoutLevel());
        $statement = $this->connection->prepare(
            "SELECT level, COUNT(*) AS total FROM log_lines{$where} GROUP BY level",
        );
        $statement->execute($params);

        $tallies = array_fill_keys(array_map(fn (StoryLevel $level): string => $level->value, StoryLevel::cases()), 0);

        /** @var array{level: string|null, total: int|string} $row */
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($row['level'] !== null && array_key_exists($row['level'], $tallies)) {
                $tallies[$row['level']] = (int) $row['total'];
            }
        }

        return $tallies;
    }

    /** One request's lines in the order they happened — `ts asc, id asc` —
     * capped at `STORY_LINE_CAP`.
     *
     * @return list<LogRow>
     */
    public function storyRows(string $requestId): array
    {
        $statement = $this->connection->prepare(
            'SELECT '.self::ROW_COLUMNS.' FROM log_lines WHERE request_id = ? ORDER BY ts ASC, id ASC LIMIT ?',
        );
        $statement->execute([$requestId, self::STORY_LINE_CAP]);

        return $this->fetchRows($statement);
    }

    /** How many lines a request holds in total — only run when the cap may
     * have hidden some of them. */
    public function storyCount(string $requestId): int
    {
        $statement = $this->connection->prepare('SELECT COUNT(*) FROM log_lines WHERE request_id = ?');
        $statement->execute([$requestId]);

        return (int) $statement->fetchColumn();
    }

    /** How many groups the current filters hold — a request counts once no
     * matter how many of its lines match. */
    public function countGroups(LogRowFilters $filters): int
    {
        return count($this->groupActivity($filters));
    }

    /**
     * One page of groups, newest activity first. Each group opens into its
     * whole request — every line the request logged, not only the ones
     * that matched the filter that surfaced it.
     *
     * @return list<LogRequestGroup>
     */
    public function groups(LogRowFilters $filters, int $limit, int $offset): array
    {
        $pageKeys = array_slice($this->groupActivity($filters), $offset, $limit);

        if ($pageKeys === []) {
            return [];
        }

        $linesByKey = [];
        foreach ($this->linesForGroupKeys(array_column($pageKeys, 'key')) as $line) {
            $linesByKey[$line->groupKey()][] = $line;
        }

        return array_map(
            fn (array $entry): LogRequestGroup => $this->summarizeGroup($entry['key'], $linesByKey[$entry['key']] ?? []),
            $pageKeys,
        );
    }

    /**
     * @return list<LogRow>
     */
    private function fetchRows(PDOStatement $statement): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return array_map(LogRow::fromDatabase(...), $rows);
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function whereClause(LogRowFilters $filters): array
    {
        [$conditions, $params] = $this->conditions($filters);

        return $conditions === [] ? ['', []] : [' WHERE '.implode(' AND ', $conditions), $params];
    }

    /**
     * @return array{0: list<string>, 1: list<mixed>}
     */
    private function conditions(LogRowFilters $filters): array
    {
        $conditions = [];
        $params = [];

        foreach ($this->columnEqualities($filters) as [$column, $value]) {
            if ($value !== null) {
                $conditions[] = "{$column} = ?";
                $params[] = $value;
            }
        }

        if ($filters->domain !== null) {
            $conditions[] = $this->domainCondition($filters->domain);
        }

        if ($filters->msg !== null) {
            $conditions[] = "log_lines.msg LIKE ? ESCAPE '\\'";
            $params[] = '%'.$this->escapeLike($filters->msg).'%';
        }

        if ($filters->from !== null) {
            $conditions[] = 'log_lines.ts >= ?';
            $params[] = $filters->from;
        }

        if ($filters->to !== null) {
            $conditions[] = 'log_lines.ts <= ?';
            $params[] = $filters->to;
        }

        if ($filters->key !== null) {
            [$attributeSql, $attributeParams] = $this->attributeCondition($filters->key, $filters->value);
            $conditions[] = $attributeSql;
            array_push($params, ...$attributeParams);
        }

        if ($filters->hideHealth) {
            $conditions[] = 'NOT '.$this->healthCheckSql();
        }

        return [$conditions, $params];
    }

    /**
     * @return list<array{0: string, 1: string|null}>
     */
    private function columnEqualities(LogRowFilters $filters): array
    {
        return [
            ['log_lines.level', $filters->level],
            ['log_lines.phase', $filters->phase],
            ['log_lines.event', $filters->event],
            ['log_lines.request_id', $filters->requestId],
            ['log_lines.txn_id', $filters->txnId],
            ['log_lines.session_id', $filters->sessionId],
            ['log_lines.actor_id', $filters->actorId],
        ];
    }

    /** `LIKE` wildcards in the searched text are matched literally. */
    private function escapeLike(string $text): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $text);
    }

    /**
     * A line's domain is its request's site: correlated on `request_id` to
     * the request's opening `http.request` line, prefix-matching that
     * line's `data.path`. A line with no `request_id` correlates to
     * nothing and matches no domain.
     */
    private function domainCondition(LogDomain $domain): string
    {
        $path = "json_extract(domainLine.data, '$.path')";

        return "EXISTS (
            SELECT 1 FROM log_lines domainLine
            WHERE domainLine.request_id = log_lines.request_id
              AND domainLine.event = 'http.request'
              AND domainLine.phase = 'will'
              AND ({$this->domainPathSql($domain, $path)})
        )";
    }

    private function domainPathSql(LogDomain $domain, string $path): string
    {
        return match ($domain) {
            LogDomain::Admin => "{$path} = '/admin' OR {$path} LIKE '/admin/%'",
            LogDomain::Seller => "{$path} = '/seller' OR {$path} LIKE '/seller/%'",
            LogDomain::Shop => "{$path} <> '".self::HEALTH_CHECK_PATH."' AND {$path} <> '".self::SHOP_EXCLUDED_PATH."'
                AND {$path} <> '/admin' AND {$path} NOT LIKE '/admin/%'
                AND {$path} <> '/seller' AND {$path} NOT LIKE '/seller/%'",
        };
    }

    /**
     * A line's request opened on the healthcheck path, by the same
     * correlation `domainCondition` uses: the request's opening
     * `http.request` will-line's `data.path`. The `CASE` guard answers
     * `NULL` rather than throwing when `data` is present but not valid
     * JSON — a line can be stored with text that never parses.
     */
    private function healthCheckSql(): string
    {
        return "EXISTS (
            SELECT 1 FROM log_lines healthLine
            WHERE healthLine.request_id = log_lines.request_id
              AND healthLine.event = 'http.request'
              AND healthLine.phase = 'will'
              AND (CASE WHEN json_valid(healthLine.data) THEN json_extract(healthLine.data, '$.path') END) = '".self::HEALTH_CHECK_PATH."'
        )";
    }

    /**
     * The any-attribute filter: a key naming a mirrored column becomes
     * that column's condition; anything else becomes `json_extract(raw,
     * ?)` with the dotted key quoted into a JSON path, so `data.*`,
     * `error.*`, and top-level extras share one code path.
     *
     * @return array{0: string, 1: list<mixed>}
     */
    private function attributeCondition(string $key, ?string $value): array
    {
        $column = self::MIRRORED_COLUMNS[$key] ?? null;
        $params = [];

        if ($column !== null) {
            $attribute = $column;
        } else {
            $attribute = 'json_extract(log_lines.raw, ?)';
            $params[] = $this->jsonPath($key);
        }

        if ($value === null) {
            return ["{$attribute} IS NOT NULL", $params];
        }

        // `json_extract` returns SQLite-typed values, so a numeric-looking
        // value is matched against both its text form and its numeric
        // form. PDO's array-execute binds every parameter as text, so the
        // numeric side is cast back to a real SQLite number in SQL — a
        // bound TEXT '1200' never equals a stored INTEGER 1200 otherwise,
        // since SQLite does not coerce across those two storage classes. A
        // JSON boolean is a stored 1 or 0 and answers the numeric side.
        if (preg_match(self::NUMERIC_VALUE, $value) === 1) {
            $params[] = $value;
            $params[] = $value;

            return ["{$attribute} IN (?, CAST(? AS REAL))", $params];
        }

        $params[] = $value;

        return ["{$attribute} = ?", $params];
    }

    /** `data.order_id` → `$."data"."order_id"` — every segment quoted, so
     * a segment can never read as JSON path syntax of its own. */
    private function jsonPath(string $key): string
    {
        $quoted = array_map(fn (string $segment): string => "\"{$segment}\"", explode('.', $key));

        return '$.'.implode('.', $quoted);
    }

    /**
     * Every group's key and most recent line's `ts`, across the whole
     * filtered set. Reads the filtered set once and groups it in memory;
     * retention bounds the table the same way the `msg` scan already
     * relies on.
     *
     * @return list<array{key: string, lastTs: string}>
     */
    private function groupActivity(LogRowFilters $filters): array
    {
        [$where, $params] = $this->whereClause($filters);
        $statement = $this->connection->prepare("SELECT id, ts, request_id FROM log_lines{$where}");
        $statement->execute($params);

        $lastTsByKey = [];

        /** @var array{id: int|string, ts: string, request_id: string|null} $row */
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = $row['request_id'] ?? self::LINE_GROUP_PREFIX.$row['id'];
            $current = $lastTsByKey[$key] ?? null;

            if ($current === null || $row['ts'] > $current) {
                $lastTsByKey[$key] = $row['ts'];
            }
        }

        $activity = [];
        foreach ($lastTsByKey as $key => $lastTs) {
            $activity[] = ['key' => $key, 'lastTs' => $lastTs];
        }

        usort($activity, function (array $a, array $b): int {
            if ($a['lastTs'] !== $b['lastTs']) {
                return $a['lastTs'] < $b['lastTs'] ? 1 : -1;
            }

            return $a['key'] < $b['key'] ? 1 : -1;
        });

        return $activity;
    }

    /**
     * Every stored line belonging to one page of group keys, in the order a
     * group opens into: `ts asc, id asc` within each request. `$keys` is
     * never empty — `groups()` already returned before reaching here — so
     * at least one of `$requestIds`/`$lineIds` always holds something.
     *
     * @param  list<string>  $keys
     * @return list<LogRow>
     */
    private function linesForGroupKeys(array $keys): array
    {
        $requestIds = [];
        $lineIds = [];

        foreach ($keys as $key) {
            if (str_starts_with($key, self::LINE_GROUP_PREFIX)) {
                $lineIds[] = (int) substr($key, strlen(self::LINE_GROUP_PREFIX));
            } else {
                $requestIds[] = $key;
            }
        }

        $clauses = [];
        $params = [];

        if ($requestIds !== []) {
            $clauses[] = 'request_id IN ('.implode(', ', array_fill(0, count($requestIds), '?')).')';
            array_push($params, ...$requestIds);
        }

        if ($lineIds !== []) {
            $clauses[] = 'id IN ('.implode(', ', array_fill(0, count($lineIds), '?')).')';
            array_push($params, ...$lineIds);
        }

        $statement = $this->connection->prepare(
            'SELECT '.self::ROW_COLUMNS.' FROM log_lines WHERE ('.implode(' OR ', $clauses).') ORDER BY ts ASC, id ASC',
        );
        $statement->execute($params);

        return $this->fetchRows($statement);
    }

    /**
     * @param  list<LogRow>  $lines
     */
    private function summarizeGroup(string $key, array $lines): LogRequestGroup
    {
        return str_starts_with($key, self::LINE_GROUP_PREFIX)
            ? $this->summarizeLineGroup($key, $lines)
            : $this->summarizeRequestGroup($key, $lines);
    }

    /**
     * @param  list<LogRow>  $lines
     */
    private function summarizeLineGroup(string $key, array $lines): LogRequestGroup
    {
        $line = $lines[0] ?? null;

        return new LogRequestGroup(
            key: $key,
            kind: 'line',
            lineCount: count($lines),
            lastTs: $line === null ? '' : $line->ts,
            method: null,
            path: null,
            status: null,
            durationMs: null,
            level: $line === null ? null : $line->level,
            msg: $line === null ? null : $line->msg,
            lines: $lines,
        );
    }

    /**
     * @param  list<LogRow>  $lines
     */
    private function summarizeRequestGroup(string $key, array $lines): LogRequestGroup
    {
        $opened = $this->firstWhere($lines, fn (LogRow $line): bool => $line->event === 'http.request' && $line->phase === 'will');
        $closed = $this->firstWhere(
            $lines,
            fn (LogRow $line): bool => $line->event === 'http.request' && ($line->phase === 'did' || $line->phase === 'failed'),
        );
        $openedData = LogRequestData::decode($opened?->data);
        $closedData = LogRequestData::decode($closed?->data);
        $last = $lines[count($lines) - 1] ?? null;

        return new LogRequestGroup(
            key: $key,
            kind: 'request',
            lineCount: count($lines),
            lastTs: $last === null ? '' : $last->ts,
            method: LogRequestData::stringField($openedData, 'method'),
            path: LogRequestData::stringField($openedData, 'path'),
            status: LogRequestData::intField($closedData, 'status'),
            durationMs: $closed?->durationMs,
            level: $closed?->level,
            msg: $closed !== null ? $closed->msg : ($opened === null ? null : $opened->msg),
            lines: $lines,
        );
    }

    /**
     * @param  list<LogRow>  $lines
     * @param  callable(LogRow): bool  $predicate
     */
    private function firstWhere(array $lines, callable $predicate): ?LogRow
    {
        foreach ($lines as $line) {
            if ($predicate($line)) {
                return $line;
            }
        }

        return null;
    }
}
