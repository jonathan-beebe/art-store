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
import type { LogLinesTable, LogsDatabase } from '../../../db/logs-schema.ts'
import type { ListPage } from '../../../core/paging/list-page.ts'

/** The story view stops here and says so; `?txn=` on the list covers the rest. */
export const STORY_LINE_CAP = 1000

/** The dotted identifier path the any-attribute filter accepts; the route
 * answers 400 for anything else. */
export const ATTRIBUTE_KEY_PATTERN = /^[A-Za-z0-9_]+(?:\.[A-Za-z0-9_]+){0,3}$/

/** The three sites `docs/alignment.md` names. A stored line carries no site
 * field of its own — `matchesDomain` derives it from its request's opening
 * `http.request` line, prefix-matching that line's `data.path`. */
export const LOG_DOMAINS = ['shop', 'seller', 'admin'] as const

export type LogDomain = (typeof LOG_DOMAINS)[number]

export type LogsContext = { logsDb: LogsDb }

/** The `?key=&value=` pair, already validated by the route. A missing value
 * asks for existence — every line that names the attribute at all. */
export type LogAttributeFilter = { key: string; value?: string }

export type LogRowFilters = {
  domain?: LogDomain
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

  if (filters.domain !== undefined) conditions.push(matchesDomain(eb, filters.domain))
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

type DomainLineFilter = ExpressionBuilder<
  LogsDatabase & { domainLine: LogLinesTable },
  'logLines' | 'domainLine'
>

/**
 * A line's domain is its request's site, so this correlates on `request_id`
 * to the request's opening `http.request` line and prefix-matches that line's
 * `data.path` — the same rule `siteActorType` applies live. A line with no
 * `request_id` (a CLI run, a boot line) correlates to nothing and matches no
 * domain.
 */
function matchesDomain(eb: LogLinesFilter, domain: LogDomain): Expression<SqlBool> {
  return eb.exists(
    eb
      .selectFrom('logLines as domainLine')
      .select('domainLine.id')
      .whereRef('domainLine.requestId', '=', 'logLines.requestId')
      .where('domainLine.event', '=', 'http.request')
      .where('domainLine.phase', '=', 'will')
      .where((inner) => domainPathCondition(inner, domain)),
  )
}

/** `shopSite`'s own SSE stream and the orchestrator's health probe sit at
 * the storefront's unprefixed root, but neither is a page a founder means by
 * "shop traffic" — excluded from the shop bucket by name. */
const SHOP_EXCLUDED_PATHS = ['/health', '/events'] as const

function domainPathCondition(eb: DomainLineFilter, domain: LogDomain): Expression<SqlBool> {
  const path = sql<string>`json_extract(${eb.ref('domainLine.data')}, '$.path')`

  if (domain === 'admin') return sql<SqlBool>`(${path} = '/admin' or ${path} like '/admin/%')`
  if (domain === 'seller') return sql<SqlBool>`(${path} = '/seller' or ${path} like '/seller/%')`

  return sql<SqlBool>`
    ${path} <> ${SHOP_EXCLUDED_PATHS[0]} and ${path} <> ${SHOP_EXCLUDED_PATHS[1]}
    and ${path} <> '/admin' and ${path} not like '/admin/%'
    and ${path} <> '/seller' and ${path} not like '/seller/%'
  `
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

/** How a group is keyed: a shared `request_id` groups every line of that
 * request; a line with none groups alone rather than by `txn_id` — a CLI or
 * boot line rarely shares a business id with another run, and grouping on
 * one would fold unrelated invocations into a single row. */
export type LogGroupKind = 'request' | 'line'

const LINE_GROUP_PREFIX = 'line:'

/** One row of the grouped view — a request's whole story, summarized the way
 * its opening and closing `http.request` lines describe it: method, path,
 * status, and duration. An orphan line summarizes itself. */
export type LogRequestGroup = {
  key: string
  kind: LogGroupKind
  lineCount: number
  lastTs: string
  method: string | null
  path: string | null
  status: number | null
  durationMs: number | null
  level: string | null
  msg: string | null
  lines: readonly LogRow[]
}

function groupKeyOf(line: Pick<LogRow, 'id' | 'requestId'>): string {
  return line.requestId ?? `${LINE_GROUP_PREFIX}${line.id}`
}

/** Every group's key and most recent line's `ts`, across the whole filtered
 * set — what `countLogGroups` counts and `logRequestGroups` pages. Reads the
 * filtered set once and groups it in memory; retention bounds the table the
 * same way the `msg` scan already relies on. */
async function groupActivity(
  context: LogsContext,
  filters: LogRowFilters,
): Promise<{ key: string; lastTs: string }[]> {
  const rows = await context.logsDb
    .selectFrom('logLines')
    .select(['id', 'ts', 'requestId'])
    .where((eb) => eb.and(matchesLogRowFilters(eb, filters)))
    .execute()

  const lastTsByKey = new Map<string, string>()
  for (const row of rows) {
    const key = groupKeyOf(row)
    const current = lastTsByKey.get(key)
    if (current === undefined || row.ts > current) lastTsByKey.set(key, row.ts)
  }

  return [...lastTsByKey.entries()]
    .map(([key, lastTs]) => ({ key, lastTs }))
    .sort((a, b) => {
      if (a.lastTs !== b.lastTs) return a.lastTs < b.lastTs ? 1 : -1
      return a.key < b.key ? 1 : -1
    })
}

/** How many groups the current filters hold — a request counts once no
 * matter how many of its lines match. */
export async function countLogGroups(
  context: LogsContext,
  filters: LogRowFilters = {},
): Promise<number> {
  return (await groupActivity(context, filters)).length
}

/**
 * One page of groups, newest activity first. Each group opens into its whole
 * request — every line the request logged, not only the ones that matched
 * the filter that surfaced it, the way opening a found Gmail thread shows the
 * whole conversation rather than just the message the search matched.
 */
export async function logRequestGroups(
  context: LogsContext,
  filters: LogRowFilters,
  page: Pick<ListPage, 'offset' | 'limit'>,
): Promise<LogRequestGroup[]> {
  const activity = await groupActivity(context, filters)
  const pageKeys = activity.slice(page.offset, page.offset + page.limit)
  if (pageKeys.length === 0) return []

  const lines = await linesForGroupKeys(context, pageKeys.map((entry) => entry.key))
  const linesByKey = new Map<string, LogRow[]>()
  for (const line of lines) {
    const key = groupKeyOf(line)
    const bucket = linesByKey.get(key)
    if (bucket === undefined) linesByKey.set(key, [line])
    else bucket.push(line)
  }

  return pageKeys.map(({ key }) => summarizeGroup(key, linesByKey.get(key) ?? []))
}

/** Every stored line belonging to one page of group keys, in the order a
 * group opens into: `ts asc, id asc` within each request. */
async function linesForGroupKeys(
  context: LogsContext,
  keys: readonly string[],
): Promise<LogRow[]> {
  const requestIds = keys.filter((key) => !key.startsWith(LINE_GROUP_PREFIX))
  const lineIds = keys
    .filter((key) => key.startsWith(LINE_GROUP_PREFIX))
    .map((key) => Number(key.slice(LINE_GROUP_PREFIX.length)))

  return context.logsDb
    .selectFrom('logLines')
    .select(ROW_COLUMNS)
    .where((eb) =>
      eb.or([
        ...(requestIds.length > 0 ? [eb('logLines.requestId', 'in', requestIds)] : []),
        ...(lineIds.length > 0 ? [eb('logLines.id', 'in', lineIds)] : []),
      ]),
    )
    .orderBy('ts', 'asc')
    .orderBy('id', 'asc')
    .execute()
}

/** The group row's summary: the root `http.request` will/did pair's method,
 * path, status, and duration for a request group; a lone line's own facts for
 * an orphan one. */
function summarizeGroup(key: string, lines: readonly LogRow[]): LogRequestGroup {
  return key.startsWith(LINE_GROUP_PREFIX)
    ? summarizeLineGroup(key, lines)
    : summarizeRequestGroup(key, lines)
}

/** A possibly-absent line's own column, read without an optional chain at
 * every call site. */
function fieldOf<Field extends keyof LogRow>(
  line: LogRow | undefined,
  field: Field,
): LogRow[Field] | null {
  return line === undefined ? null : line[field]
}

function summarizeLineGroup(key: string, lines: readonly LogRow[]): LogRequestGroup {
  const line = lines.at(0)

  return {
    key,
    kind: 'line',
    lineCount: lines.length,
    lastTs: fieldOf(line, 'ts') ?? '',
    method: null,
    path: null,
    status: null,
    durationMs: null,
    level: fieldOf(line, 'level'),
    msg: fieldOf(line, 'msg'),
    lines,
  }
}

function isRootWill(line: LogRow): boolean {
  return line.event === 'http.request' && line.phase === 'will'
}

function isRootClose(line: LogRow): boolean {
  return line.event === 'http.request' && (line.phase === 'did' || line.phase === 'failed')
}

/** The root pair's headline: the closing line's, falling back to the
 * opening line's when the request has not closed within the cap. */
function rootMsg(opened: LogRow | undefined, closed: LogRow | undefined): string | null {
  return fieldOf(closed, 'msg') ?? fieldOf(opened, 'msg')
}

function summarizeRequestGroup(key: string, lines: readonly LogRow[]): LogRequestGroup {
  const opened = lines.find(isRootWill)
  const closed = lines.find(isRootClose)
  const openedData = parsedData(fieldOf(opened, 'data'))
  const closedData = parsedData(fieldOf(closed, 'data'))

  return {
    key,
    kind: 'request',
    lineCount: lines.length,
    lastTs: fieldOf(lines.at(-1), 'ts') ?? '',
    method: stringField(openedData, 'method'),
    path: stringField(openedData, 'path'),
    status: numberField(closedData, 'status'),
    durationMs: fieldOf(closed, 'durationMs'),
    level: fieldOf(closed, 'level'),
    msg: rootMsg(opened, closed),
    lines,
  }
}

/** Stored `data`, parsed for the fields a group summary reads off it. The
 * mirror invariant means a line can be stored with text that never parses. */
function parsedData(text: string | null): Record<string, unknown> {
  if (text === null) return {}
  try {
    return JSON.parse(text) as Record<string, unknown>
  } catch {
    return {}
  }
}

function stringField(data: Record<string, unknown>, field: string): string | null {
  const value = data[field]
  return typeof value === 'string' ? value : null
}

function numberField(data: Record<string, unknown>, field: string): number | null {
  const value = data[field]
  return typeof value === 'number' ? value : null
}
