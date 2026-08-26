/**
 * Every JSON line pino writes, mirrored into a queryable SQLite file.
 * `docs/log-store.md` is the contract; its three invariants shape everything
 * here: stdout is written first and verbatim, the store keeps what was emitted
 * (unparseable lines included), and no store failure ever escapes into the
 * app — a broken store degrades to stdout-only logging.
 */
import { DatabaseSync } from 'node:sqlite'
import type { StatementSync } from 'node:sqlite'
import type pino from 'pino'
import type { NewLogLine } from './db/logs-schema.ts'

/** Bumped when the DDL changes. A file ahead of this build is left untouched
 * and the store is disabled for the process — deleting the file is the
 * sanctioned escape hatch for a mismatch. */
const SCHEMA_VERSION = 1

/** A contended flush must fail fast and re-buffer; the commerce connection's
 * 5000ms would stall the event loop for the whole wait. */
const BUSY_TIMEOUT_MS = 250

/** Buffered lines that flush immediately instead of waiting for the macrotask —
 * also the row count one multi-row INSERT statement carries at most, keeping
 * its bound parameters well under SQLite's variable limit. */
const FLUSH_AT = 256

/** Past this many buffered lines the store drops new ones — stdout still
 * carried them — until a flush succeeds. */
const BUFFER_CAP = 10_000

/** Stored `raw` loses tail bytes past this. The mirrored columns are extracted
 * from the full line first, so a pathological payload keeps its queryable facts. */
const RAW_CAP_BYTES = 64 * 1024

/** Rows one retention DELETE takes at most, so the write lock is held for
 * milliseconds per batch and a concurrently flushing server re-buffers at
 * most one tick. */
const PRUNE_BATCH = 5000

/** Freed pages `incremental_vacuum` hands back per sweep; the bootstrap's
 * `auto_vacuum = INCREMENTAL` is what makes the pragma work at all. */
const VACUUM_PAGES = 1000

const DDL = `
  CREATE TABLE IF NOT EXISTS log_lines (
    id          INTEGER PRIMARY KEY,
    ts          TEXT NOT NULL,
    level       TEXT,
    event       TEXT,
    phase       TEXT,
    msg         TEXT,
    request_id  TEXT,
    session_id  TEXT,
    actor_type  TEXT,
    actor_id    TEXT,
    txn_id      TEXT,
    duration_ms INTEGER,
    data        TEXT,
    error       TEXT,
    raw         TEXT NOT NULL
  );

  CREATE INDEX IF NOT EXISTS log_lines_ts         ON log_lines (ts);
  CREATE INDEX IF NOT EXISTS log_lines_event_ts   ON log_lines (event, ts);
  CREATE INDEX IF NOT EXISTS log_lines_level_ts   ON log_lines (level, ts);
  CREATE INDEX IF NOT EXISTS log_lines_request_id ON log_lines (request_id) WHERE request_id IS NOT NULL;
  CREATE INDEX IF NOT EXISTS log_lines_txn_id     ON log_lines (txn_id)     WHERE txn_id     IS NOT NULL;
  CREATE INDEX IF NOT EXISTS log_lines_actor_id   ON log_lines (actor_id)   WHERE actor_id   IS NOT NULL;
`

const INSERT_COLUMNS =
  'ts, level, event, phase, msg, request_id, session_id, actor_type, actor_id, txn_id, duration_ms, data, error, raw'

const ROW_PLACEHOLDERS = `(${INSERT_COLUMNS.split(', ').map(() => '?').join(', ')})`

export type LogStore = {
  /** The open handle the batch writer prepares against; the admin reader wraps
   * the same one, so reads and the writer serialize instead of racing. Null
   * when the store is disabled — the file could not be opened or is ahead of
   * this build — and stdout is the only destination left. */
  database: DatabaseSync | null
  append: (line: NewLogLine) => void
  flushSync: () => void
  close: () => void
}

/**
 * Opens (bootstrapping if needed) the log store over its own `DatabaseSync`
 * handle and registers a final flush on process exit — `DatabaseSync` is
 * synchronous, so a fast-exiting CLI's last lines survive without the CLI
 * knowing the store exists. Any open failure returns a disabled store after
 * one `app.log` warn line on stdout; nothing thrown here reaches the caller.
 * `stdout` is injectable so a test can watch the warn line.
 */
export function openLogStore(
  file: string,
  { stdout = process.stdout }: { stdout?: { write(chunk: string): unknown } } = {},
): LogStore {
  let database: DatabaseSync | null = null

  try {
    database = new DatabaseSync(file)
    database.exec('PRAGMA journal_mode = WAL')
    database.exec('PRAGMA synchronous = NORMAL')
    database.exec(`PRAGMA busy_timeout = ${BUSY_TIMEOUT_MS}`)
    ensureLogSchema(database)
  } catch (error) {
    closeQuietly(database)
    warnStoreDisabled(stdout, file, error)

    return { database: null, append() {}, flushSync() {}, close() {} }
  }

  return openedLogStore(database)
}

/**
 * Deletes every stored line whose `ts` is strictly before the cutoff, in
 * `batchSize`-row batches looped until no rows change, then hands freed pages
 * back with `PRAGMA incremental_vacuum`. `docs/alignment.md` §2.3 has no
 * event for this write, so — like the rate-limit prune it sits beside in the
 * sweep CLI — it stays silent. A disabled store prunes nothing. Returns the
 * rows deleted. `batchSize` is injectable so a test can watch the loop
 * without seeding thousands of rows.
 */
export function pruneLogLines(
  store: LogStore,
  cutoff: Date,
  { batchSize = PRUNE_BATCH }: { batchSize?: number } = {},
): number {
  if (store.database === null) return 0

  const batch = store.database.prepare(
    'DELETE FROM log_lines WHERE id IN (SELECT id FROM log_lines WHERE ts < ? LIMIT ?)',
  )
  const cutoffTs = cutoff.toISOString()
  let deleted = 0

  for (;;) {
    const changes = Number(batch.run(cutoffTs, batchSize).changes)
    if (changes === 0) break

    deleted += changes
  }

  store.database.exec(`PRAGMA incremental_vacuum(${VACUUM_PAGES})`)

  return deleted
}

/**
 * A `pino.DestinationStream` that writes each chunk to stdout first and
 * verbatim, then parses complete lines into the store's buffer, carrying a
 * trailing partial to the next write. The whole body is guarded: the store's
 * own failures go to stderr (stdout is the §2 surface, and logging them
 * through pino would feed the store its own errors), and nothing propagates
 * to pino. `stdout` is injectable so a test can watch the passthrough.
 */
export function logStoreStream(
  store: LogStore,
  { stdout = process.stdout }: { stdout?: { write(chunk: string): unknown } } = {},
): pino.DestinationStream {
  let remainder = ''

  return {
    write(chunk: string): void {
      try {
        stdout.write(chunk)
      } catch {
        // stdout refusing a chunk must not stop the mirror or reach pino.
      }

      if (store.database === null) return

      try {
        remainder = ingest(store, remainder + chunk)
      } catch (error) {
        reportFailure(error)
      }
    },
  }
}

/** Appends every complete line in `text` and returns the trailing partial. */
function ingest(store: LogStore, text: string): string {
  const lines = text.split('\n')
  const remainder = lines.pop() ?? ''

  for (const line of lines) {
    if (line.length > 0) store.append(parsedLine(line))
  }

  return remainder
}

/**
 * The eleven §2.1 fields mapped to their columns, `data`/`error` re-serialized
 * as JSON text, and the whole line as capped `raw`. A line that does not parse
 * as a JSON object is stored as `raw` plus a receive-time `ts` with every
 * other column null — the store is a mirror, not a validator.
 */
function parsedLine(line: string): NewLogLine {
  const fields = parsedObject(line)
  if (fields === null) return { ...EMPTY_LINE, ts: new Date().toISOString(), raw: cappedRaw(line) }

  return {
    ts: stringOrNull(fields.ts) ?? new Date().toISOString(),
    level: stringOrNull(fields.level),
    event: stringOrNull(fields.event),
    phase: stringOrNull(fields.phase),
    msg: stringOrNull(fields.msg),
    requestId: stringOrNull(fields.request_id),
    sessionId: stringOrNull(fields.session_id),
    actorType: stringOrNull(fields.actor_type),
    actorId: stringOrNull(fields.actor_id),
    txnId: stringOrNull(fields.txn_id),
    durationMs: typeof fields.duration_ms === 'number' ? fields.duration_ms : null,
    data: jsonTextOrNull(fields.data),
    error: jsonTextOrNull(fields.error),
    raw: cappedRaw(line),
  }
}

const EMPTY_LINE: Omit<NewLogLine, 'ts' | 'raw'> = {
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
}

function parsedObject(line: string): Record<string, unknown> | null {
  let parsed: unknown

  try {
    parsed = JSON.parse(line)
  } catch {
    return null
  }

  if (typeof parsed !== 'object' || parsed === null || Array.isArray(parsed)) return null

  return parsed as Record<string, unknown>
}

function stringOrNull(value: unknown): string | null {
  return typeof value === 'string' ? value : null
}

function jsonTextOrNull(value: unknown): string | null {
  return value === undefined ? null : JSON.stringify(value)
}

function cappedRaw(line: string): string {
  if (Buffer.byteLength(line) <= RAW_CAP_BYTES) return line

  return Buffer.from(line).toString('utf8', 0, RAW_CAP_BYTES)
}

/**
 * The buffering writer over an open handle. Appends buffer in memory; a flush
 * is one `BEGIN IMMEDIATE`, prepared multi-row INSERTs in `FLUSH_AT`-row
 * chunks, one `COMMIT` — one WAL append per batch, off the request path. On
 * any flush error the batch re-buffers and waits for the next scheduled
 * flush; new appends keep scheduling them, so a transient `SQLITE_BUSY`
 * retries a tick later and a dead handle never busy-loops the process.
 */
function openedLogStore(database: DatabaseSync): LogStore {
  const statements = new Map<number, StatementSync>()
  let buffer: NewLogLine[] = []
  let flushScheduled = false
  let waitingForRetry = false
  let announcedDrop = false
  let closed = false

  const preparedInsert = (rows: number): StatementSync => {
    const cached = statements.get(rows)
    if (cached !== undefined) return cached

    const placeholders = Array.from({ length: rows }, () => ROW_PLACEHOLDERS).join(', ')
    const statement = database.prepare(`INSERT INTO log_lines (${INSERT_COLUMNS}) VALUES ${placeholders}`)
    statements.set(rows, statement)

    return statement
  }

  const insertBatch = (batch: NewLogLine[]): void => {
    database.exec('BEGIN IMMEDIATE')

    try {
      for (let start = 0; start < batch.length; start += FLUSH_AT) {
        const rows = batch.slice(start, start + FLUSH_AT)
        preparedInsert(rows.length).run(...rows.flatMap(columnValues))
      }
      database.exec('COMMIT')
    } catch (error) {
      rollbackQuietly(database)
      throw error
    }
  }

  const flush = (): void => {
    if (closed || buffer.length === 0) return

    const batch = buffer
    buffer = []

    try {
      insertBatch(batch)
      waitingForRetry = false
      announcedDrop = false
    } catch (error) {
      buffer = batch.concat(buffer)
      waitingForRetry = true
      reportFailure(error)
    }
  }

  const scheduleFlush = (): void => {
    if (flushScheduled) return

    flushScheduled = true
    setImmediate(() => {
      flushScheduled = false
      waitingForRetry = false
      flush()
    })
  }

  const append = (line: NewLogLine): void => {
    if (closed) return

    if (buffer.length >= BUFFER_CAP) {
      announceDrop()
      return
    }

    buffer.push(line)

    if (buffer.length >= FLUSH_AT && !waitingForRetry) {
      flush()
      return
    }

    scheduleFlush()
  }

  const announceDrop = (): void => {
    if (announcedDrop) return

    announcedDrop = true
    reportFailure(
      new Error(`buffer full at ${BUFFER_CAP} lines; dropping new lines until a flush succeeds`),
    )
  }

  const flushOnExit = (): void => {
    flush()
  }
  process.once('exit', flushOnExit)

  const close = (): void => {
    if (closed) return

    flush()
    closed = true
    process.removeListener('exit', flushOnExit)
    closeQuietly(database)
  }

  return { database, append, flushSync: flush, close }
}

/** The bound values one buffered line contributes, in `INSERT_COLUMNS` order. */
function columnValues(line: NewLogLine): Array<string | number | null> {
  return [
    line.ts,
    line.level,
    line.event,
    line.phase,
    line.msg,
    line.requestId,
    line.sessionId,
    line.actorType,
    line.actorId,
    line.txnId,
    line.durationMs,
    line.data,
    line.error,
    line.raw,
  ]
}

/**
 * Ensure-on-open schema, versioned by `PRAGMA user_version`. `BEGIN IMMEDIATE`
 * plus `IF NOT EXISTS` makes a simultaneous server-and-CLI first boot safe:
 * one process bootstraps, the other waits out the busy timeout and no-ops. A
 * file ahead of this build throws, which disables the store for the process.
 */
function ensureLogSchema(database: DatabaseSync): void {
  const version = userVersion(database)

  if (version > SCHEMA_VERSION) {
    throw new Error(`the file is at schema version ${version}; this build reads ${SCHEMA_VERSION}`)
  }
  if (version === SCHEMA_VERSION) return

  database.exec('BEGIN IMMEDIATE')

  try {
    // Before the DDL: auto_vacuum only takes hold while no tables exist, and
    // it is what lets the retention sweep's incremental_vacuum return pages.
    database.exec('PRAGMA auto_vacuum = INCREMENTAL')
    database.exec(DDL)
    database.exec(`PRAGMA user_version = ${SCHEMA_VERSION}`)
    database.exec('COMMIT')
  } catch (error) {
    rollbackQuietly(database)
    throw error
  }
}

function userVersion(database: DatabaseSync): number {
  const row = database.prepare('PRAGMA user_version').get() as { user_version?: unknown } | undefined

  return typeof row?.user_version === 'number' ? row.user_version : 0
}

/** One §2-shaped `app.log` warn line on stdout: the app boots, the store sits
 * this process out, and the operator can read why. */
function warnStoreDisabled(
  stdout: { write(chunk: string): unknown },
  file: string,
  error: unknown,
): void {
  const line = JSON.stringify({
    ts: new Date().toISOString(),
    level: 'warn',
    event: 'app.log',
    msg: `⚠️ log store disabled: ${errorMessage(error)}`,
    data: { log_database_file: file },
  })

  try {
    stdout.write(`${line}\n`)
  } catch {
    // Even a refusing stdout must not turn a degraded store into a crash.
  }
}

/** The store's own failures go to stderr — stdout is the §2 surface, and pino
 * would feed the store its own errors. */
function reportFailure(error: unknown): void {
  try {
    process.stderr.write(`log store: ${errorMessage(error)}\n`)
  } catch {
    // Nothing left to tell and nobody to throw at.
  }
}

function errorMessage(error: unknown): string {
  return error instanceof Error ? error.message : String(error)
}

function rollbackQuietly(database: DatabaseSync): void {
  try {
    database.exec('ROLLBACK')
  } catch {
    // The transaction already rolled back with the failure itself.
  }
}

function closeQuietly(database: DatabaseSync | null): void {
  try {
    database?.close()
  } catch {
    // A handle that failed to open may refuse to close; either way it is gone.
  }
}
