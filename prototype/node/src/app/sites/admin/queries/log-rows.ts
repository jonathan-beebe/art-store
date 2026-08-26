/**
 * What `/admin/logs` and the story view read out of the log store
 * (`docs/log-store.md` § Viewer). Every read goes through
 * `matchesLogRowFilters`, so the count, the page, the level tallies, and the
 * story all agree on what a filter means.
 */
import { sql } from 'kysely'
import type { Expression, ExpressionBuilder, SqlBool } from 'kysely'
import { tallyOver, type Tally } from '../../../core/analytics/tally.ts'
import {
  LOG_LINE_LEVELS,
  type LogEvent,
  type LogLineLevel,
  type LogPhase,
} from '../../../core/logging/log-event.ts'
import { toCount } from '../../../db/count.ts'
import type { LogsDb } from '../../../db/database.ts'
import type { LogsDatabase } from '../../../db/logs-schema.ts'
import type { ListPage } from '../../../core/paging/list-page.ts'

/** The story view stops here and says so; `?txn=` on the list covers the rest. */
export const STORY_LINE_CAP = 1000

/** The dotted identifier path the any-attribute filter accepts; the route
 * answers 400 for anything else. */
export const ATTRIBUTE_KEY_PATTERN = /^[A-Za-z0-9_]+(?:\.[A-Za-z0-9_]+){0,3}$/

export type LogsContext = { logsDb: LogsDb }

/** The `?key=&value=` pair, already validated by the route. A missing value
 * asks for existence — every line that names the attribute at all. */
export type LogAttributeFilter = { key: string; value?: string }

export type LogRowFilters = {
  level?: LogLineLevel
  phase?: LogPhase
  event?: LogEvent
  requestId?: string
  txnId?: string
  sessionId?: string
  actorId?: string
  /** Substring of `msg` — a scan, fine at a retention-bounded table's size. */
  msg?: string
  /** ISO instants compared lexically against `ts`: fixed ISO-8601 UTC text
   * sorts chronologically. */
  from?: string
  to?: string
  attribute?: LogAttributeFilter
}

/** A stored line as the two pages show it — every mirrored column, without `raw`. */
export type LogRow = {
  id: number
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
  data: string | null
  error: string | null
}

const ROW_COLUMNS = [
  'id',
  'ts',
  'level',
  'event',
  'phase',
  'msg',
  'requestId',
  'sessionId',
  'actorType',
  'actorId',
  'txnId',
  'durationMs',
  'data',
  'error',
] as const

type LogLinesFilter = ExpressionBuilder<LogsDatabase, 'logLines'>

/** The columns the any-attribute filter short-circuits to, keyed as a log
 * line names them, so the indexes serve a key that has one. */
const MIRRORED_COLUMNS = {
  ts: 'logLines.ts',
  level: 'logLines.level',
  event: 'logLines.event',
  phase: 'logLines.phase',
  msg: 'logLines.msg',
  request_id: 'logLines.requestId',
  session_id: 'logLines.sessionId',
  actor_type: 'logLines.actorType',
  actor_id: 'logLines.actorId',
  txn_id: 'logLines.txnId',
  duration_ms: 'logLines.durationMs',
} as const

/** A JSON number and a string that looks like one both answer a numeric value. */
const NUMERIC_VALUE = /^-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?$/

/** `LIKE` wildcards in the searched text are matched literally. */
const LIKE_ESCAPED = /[\\%_]/g

export function matchesLogRowFilters(
  eb: LogLinesFilter,
  filters: LogRowFilters,
): Expression<SqlBool>[] {
  const conditions = columnEqualities(eb, filters)

  if (filters.msg !== undefined) conditions.push(msgContains(eb, filters.msg))
  if (filters.from !== undefined) conditions.push(eb('logLines.ts', '>=', filters.from))
  if (filters.to !== undefined) conditions.push(eb('logLines.ts', '<=', filters.to))
  if (filters.attribute !== undefined) {
    conditions.push(matchesAttribute(eb, filters.attribute))
  }

  return conditions
}

type TextColumn =
  | 'logLines.level'
  | 'logLines.phase'
  | 'logLines.event'
  | 'logLines.requestId'
  | 'logLines.txnId'
  | 'logLines.sessionId'
  | 'logLines.actorId'

/** The filters that are one bound equality on a mirrored column. */
function columnEqualities(eb: LogLinesFilter, filters: LogRowFilters): Expression<SqlBool>[] {
  const equalities: [TextColumn, string | undefined][] = [
    ['logLines.level', filters.level],
    ['logLines.phase', filters.phase],
    ['logLines.event', filters.event],
    ['logLines.requestId', filters.requestId],
    ['logLines.txnId', filters.txnId],
    ['logLines.sessionId', filters.sessionId],
    ['logLines.actorId', filters.actorId],
  ]
  const conditions: Expression<SqlBool>[] = []

  for (const [column, value] of equalities) {
    if (value !== undefined) conditions.push(eb(column, '=', value))
  }

  return conditions
}

function msgContains(eb: LogLinesFilter, text: string): Expression<SqlBool> {
  const pattern = `%${text.replace(LIKE_ESCAPED, (wildcard) => `\\${wildcard}`)}%`

  return sql<SqlBool>`${eb.ref('logLines.msg')} like ${pattern} escape '\\'`
}

/**
 * The any-attribute filter, compiled per `docs/log-store.md`: a key naming a
 * mirrored column becomes that column's condition; anything else becomes
 * `json_extract(raw, ?)` with the dotted key quoted into a JSON path, so
 * `data.*`, `error.*`, and top-level extras like `pid` share one code path.
 */
function matchesAttribute(
  eb: LogLinesFilter,
  { key, value }: LogAttributeFilter,
): Expression<SqlBool> {
  const column = MIRRORED_COLUMNS[key as keyof typeof MIRRORED_COLUMNS]
  const attribute =
    column !== undefined
      ? eb.ref(column)
      : sql`json_extract(${eb.ref('logLines.raw')}, ${jsonPath(key)})`

  if (value === undefined) return sql<SqlBool>`${attribute} is not null`

  // `json_extract` returns SQLite-typed values, so a numeric-looking value is
  // bound as both text and number rather than cast; a JSON boolean is a
  // stored 1 or 0 and answers the numeric side.
  if (NUMERIC_VALUE.test(value)) {
    return sql<SqlBool>`${attribute} in (${value}, ${Number(value)})`
  }

  return sql<SqlBool>`${attribute} = ${value}`
}

/** `data.order_id` → `$."data"."order_id"` — every segment quoted, so a
 * segment can never read as JSON path syntax of its own. */
function jsonPath(key: string): string {
  return `$${key
    .split('.')
    .map((segment) => `."${segment}"`)
    .join('')}`
}

/** How many lines match `filters`, independent of which page of them is shown. */
export async function countLogRows(
  context: LogsContext,
  filters: LogRowFilters = {},
): Promise<number> {
  const counted = await context.logsDb
    .selectFrom('logLines')
    .select(({ fn }) => fn.countAll<string | number | bigint>().as('total'))
    .where((eb) => eb.and(matchesLogRowFilters(eb, filters)))
    .executeTakeFirstOrThrow()

  return toCount(counted.total)
}

/** One page of matching lines, newest first — `ts desc` with the rowid as the
 * tiebreak within one millisecond. */
export async function logRows(
  context: LogsContext,
  filters: LogRowFilters = {},
  page: Pick<ListPage, 'offset' | 'limit'>,
): Promise<LogRow[]> {
  return context.logsDb
    .selectFrom('logLines')
    .select(ROW_COLUMNS)
    .where((eb) => eb.and(matchesLogRowFilters(eb, filters)))
    .orderBy('ts', 'desc')
    .orderBy('id', 'desc')
    .offset(page.offset)
    .limit(page.limit)
    .execute()
}

/**
 * How many lines each level holds under the current filters minus `level`
 * itself, so the four stat tiles double as the level filter's fast path. Every
 * level answers, zero included.
 */
export async function logLevelTallies(
  context: LogsContext,
  filters: LogRowFilters = {},
): Promise<readonly Tally<LogLineLevel>[]> {
  const rows = await context.logsDb
    .selectFrom('logLines')
    .select(['level', ({ fn }) => fn.countAll<string | number | bigint>().as('total')])
    .where((eb) => eb.and(matchesLogRowFilters(eb, { ...filters, level: undefined })))
    .groupBy('level')
    .execute()

  const counted = rows
    .filter((row): row is typeof row & { level: LogLineLevel } =>
      (LOG_LINE_LEVELS as readonly string[]).includes(row.level ?? ''),
    )
    .map((row) => ({ key: row.level, count: toCount(row.total) }))

  return tallyOver(LOG_LINE_LEVELS, counted)
}

/** One request's lines in the order they happened — `ts asc, id asc` — capped
 * at `STORY_LINE_CAP`; the page says so when the cap bites. */
export async function requestStoryRows(
  context: LogsContext,
  requestId: string,
): Promise<LogRow[]> {
  return context.logsDb
    .selectFrom('logLines')
    .select(ROW_COLUMNS)
    .where('requestId', '=', requestId)
    .orderBy('ts', 'asc')
    .orderBy('id', 'asc')
    .limit(STORY_LINE_CAP)
    .execute()
}
