/**
 * The log database as Kysely sees it — the one `log_lines` table
 * `docs/log-store.md` defines, mirroring every stdout line. Its DDL lives in
 * `app/log-store.ts` (ensure-on-open, versioned by `PRAGMA user_version`); the
 * migrations directory belongs to the commerce database and admits nothing
 * else. Row types are camelCase here while the DDL creates snake_case columns,
 * the same `CamelCasePlugin` convention `schema.ts` follows.
 */
import type { Generated } from 'kysely'

export type LogLinesTable = {
  /** Rowid: arrival order, the tiebreak within one `ts` millisecond. Log rows
   * are telemetry nothing references, so this is the stated exception to the
   * prefixed-ULID rule in `docs/alignment.md` §1. */
  id: Generated<number>
  /** The line's own `ts`; receive time when the line would not parse. */
  ts: string
  level: string | null
  event: string | null
  phase: string | null
  msg: string | null
  requestId: string | null
  sessionId: string | null
  actorType: string | null
  actorId: string | null
  txnId: string | null
  durationMs: number | null
  /** JSON text of the line's `data`, null when absent. */
  data: string | null
  /** JSON text of the line's `error`, null when absent. */
  error: string | null
  /** The stdout line, verbatim, capped at 64 KiB. */
  raw: string
}

export type LogsDatabase = {
  logLines: LogLinesTable
}

/** The row the ingest INSERT writes: every column but the rowid the database
 * mints. Nullable columns stay required here — a line that lacks a field
 * stores an explicit null, per the mirror invariant. */
export type NewLogLine = Omit<LogLinesTable, 'id'>
