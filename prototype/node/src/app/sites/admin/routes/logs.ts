import { z } from 'zod'
import {
  LOG_EVENTS,
  LOG_LINE_LEVELS,
  LOG_PHASES,
  type LogLineLevel,
} from '../../../core/logging/log-event.ts'
import { isAcceptableRequestId } from '../../../core/logging/request-id.ts'
import { listPage } from '../../../core/paging/list-page.ts'
import { idValue, optionalFilter } from '../../../http/request-schema.ts'
import type { ZodRoutes } from '../../../http/zod-type-provider.ts'
import { adminPage } from '../page.ts'
import {
  ATTRIBUTE_KEY_PATTERN,
  countLogGroups,
  countLogRows,
  LOG_DOMAINS,
  logLevelTallies,
  logRequestGroups,
  logRows,
  requestStoryRows,
  STORY_LINE_CAP,
  type LogRow,
  type LogRowFilters,
  type LogsContext,
} from '../queries/log-rows.ts'

// One screen of the log table.
const ROWS_PER_PAGE = 50

/** What each level's stat tile is titled. */
const LEVEL_TILES: readonly { level: LogLineLevel; label: string }[] = [
  { level: 'error', label: 'Errors' },
  { level: 'warn', label: 'Warnings' },
  { level: 'info', label: 'Info' },
  { level: 'debug', label: 'Debug' },
]

const requestIdValue = z
  .string()
  .refine(isAcceptableRequestId, { message: 'not a request id' })

const logsQuery = z
  .object({
    domain: optionalFilter(z.enum(LOG_DOMAINS)),
    level: optionalFilter(z.enum(LOG_LINE_LEVELS)),
    phase: optionalFilter(z.enum(LOG_PHASES)),
    event: optionalFilter(z.enum(LOG_EVENTS)),
    request: optionalFilter(requestIdValue),
    txn: optionalFilter(idValue('txn')),
    session: optionalFilter(idValue('ses')),
    actor: optionalFilter(z.union([idValue('adm'), idValue('sel'), idValue('cus')])),
    msg: optionalFilter(z.string()),
    from: optionalFilter(z.iso.datetime({ message: 'not an ISO instant' })),
    to: optionalFilter(z.iso.datetime({ message: 'not an ISO instant' })),
    key: optionalFilter(z.string().regex(ATTRIBUTE_KEY_PATTERN, 'not a dotted attribute path')),
    value: optionalFilter(z.string()),
    group: optionalFilter(z.literal('1')),
    health: optionalFilter(z.literal('1')),
    page: z.string().optional(),
  })
  // A value with no key names no attribute to compare it against.
  .refine((query) => query.value === undefined || query.key !== undefined, {
    message: 'a value filter needs a key',
    path: ['value'],
  })

type LogsQuery = z.output<typeof logsQuery>

/** The submitted filters, without the page — what round-trips through the
 * form, the pager, and the level tiles. */
function filterFields(query: LogsQuery): Record<string, string | undefined> {
  const {
    domain,
    level,
    phase,
    event,
    request,
    txn,
    session,
    actor,
    msg,
    from,
    to,
    key,
    value,
    group,
    health,
  } = query

  return {
    domain,
    level,
    phase,
    event,
    request,
    txn,
    session,
    actor,
    msg,
    from,
    to,
    key,
    value,
    group,
    health,
  }
}

function definedEntries(fields: Record<string, string | undefined>): [string, string][] {
  return Object.entries(fields).filter(
    (entry): entry is [string, string] => entry[1] !== undefined,
  )
}

function filtersOf(query: LogsQuery): LogRowFilters {
  return {
    domain: query.domain,
    level: query.level,
    phase: query.phase,
    event: query.event,
    requestId: query.request,
    txnId: query.txn,
    sessionId: query.session,
    actorId: query.actor,
    msg: query.msg,
    from: query.from,
    to: query.to,
    attribute: query.key === undefined ? undefined : { key: query.key, value: query.value },
    hideHealth: query.health !== '1',
  }
}

/** The four tiles, each linking to the same query with `level` set. */
async function levelTiles(context: LogsContext, query: LogsQuery, filters: LogRowFilters) {
  const tallies = await logLevelTallies(context, filters)
  const withoutLevel = definedEntries({ ...filterFields(query), level: undefined })

  return LEVEL_TILES.map(({ level, label }) => ({
    level,
    label,
    count: tallies.find((tally) => tally.key === level)?.count ?? 0,
    href: `/admin/logs?${new URLSearchParams([...withoutLevel, ['level', level]]).toString()}`,
  }))
}

type StoryHeader = {
  firstTs: string | null
  lastTs: string | null
  durationMs: number | null
  sessionId: string | null
  actorType: string | null
  actorId: string | null
}

const EMPTY_STORY_HEADER: StoryHeader = {
  firstTs: null,
  lastTs: null,
  durationMs: null,
  sessionId: null,
  actorType: null,
  actorId: null,
}

/** The facts the story header states, read off the capped lines. The root
 * `did` line is the one 🟢 marks — the process close, per §2.4. */
function storyHeader(lines: readonly LogRow[]): StoryHeader {
  const first = lines.at(0)
  if (first === undefined) return EMPTY_STORY_HEADER

  const last = lines.at(-1)
  const withSession = lines.find((line) => line.sessionId !== null)
  const withActor = lines.find((line) => line.actorId !== null)
  const rootClose = lines.find((line) => line.msg !== null && line.msg.startsWith('🟢'))

  return {
    firstTs: first.ts,
    lastTs: last === undefined ? null : last.ts,
    durationMs: rootClose === undefined ? null : rootClose.durationMs,
    sessionId: withSession === undefined ? null : withSession.sessionId,
    actorType: withActor === undefined ? null : withActor.actorType,
    actorId: withActor === undefined ? null : withActor.actorId,
  }
}

export const logRoutes: ZodRoutes = (admin, _options, done) => {
  admin.get('/logs', { schema: { querystring: logsQuery } }, async (request, reply) => {
    const { logsDb } = admin
    const query = request.query
    const filters = filterFields(query)

    if (logsDb === null) {
      return reply.render(
        'logs',
        adminPage('Logs', { storeAvailable: false, lines: [], filters }),
      )
    }

    const context = { logsDb }
    const rowFilters = filtersOf(query)
    const grouped = query.group === '1'
    const page = listPage({
      requested: query.page,
      size: ROWS_PER_PAGE,
      totalCount: grouped
        ? await countLogGroups(context, rowFilters)
        : await countLogRows(context, rowFilters),
    })

    return reply.render(
      'logs',
      adminPage('Logs', {
        storeAvailable: true,
        grouped,
        lines: grouped ? [] : await logRows(context, rowFilters, page),
        groups: grouped ? await logRequestGroups(context, rowFilters, page) : [],
        tiles: await levelTiles(context, query, rowFilters),
        filters,
        domains: LOG_DOMAINS,
        levels: LOG_LINE_LEVELS,
        phases: LOG_PHASES,
        events: LOG_EVENTS,
        page,
        filterQuery: new URLSearchParams(definedEntries(filters)).toString(),
      }),
    )
  })

  admin.get(
    '/logs/requests/:requestId',
    // A refused segment reaches the site's 404 page, the way every route's
    // `params` schema does.
    { schema: { params: z.object({ requestId: requestIdValue }) } },
    async (request, reply) => {
      const { logsDb } = admin
      const { requestId } = request.params

      if (logsDb === null) {
        return reply.render(
          'log-story',
          adminPage(`Request ${requestId}`, {
            storeAvailable: false,
            requestId,
            lines: [],
            totalCount: 0,
            lineCap: STORY_LINE_CAP,
            header: storyHeader([]),
          }),
        )
      }

      const context = { logsDb }
      const lines = await requestStoryRows(context, requestId)
      // The count query only runs when the cap may have hidden lines.
      const totalCount =
        lines.length < STORY_LINE_CAP
          ? lines.length
          : await countLogRows(context, { requestId })

      return reply.render(
        'log-story',
        adminPage(`Request ${requestId}`, {
          storeAvailable: true,
          requestId,
          lines,
          totalCount,
          lineCap: STORY_LINE_CAP,
          header: storyHeader(lines),
        }),
      )
    },
  )

  done()
}
