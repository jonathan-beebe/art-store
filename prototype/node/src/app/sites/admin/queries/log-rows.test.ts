import { test } from 'node:test'
import assert from 'node:assert/strict'
import { openLogsDatabase } from '../../../db/database.ts'
import type { NewLogLine } from '../../../db/logs-schema.ts'
import { openLogStore, type LogStore } from '../../../log-store.ts'
import { storedLogLine } from '../../../test/log-lines.ts'
import {
  countLogGroups,
  countLogRows,
  logLevelTallies,
  logRequestGroups,
  logRows,
  requestStoryRows,
  STORY_LINE_CAP,
  type LogsContext,
} from './log-rows.ts'

const FULL_PAGE = { offset: 0, limit: 10_000 }

type LogsWorld = {
  context: LogsContext
  store: LogStore
  close: () => Promise<void>
}

/** An in-memory store with the reader wrapped over the writer's own handle. */
function openLogsWorld(): LogsWorld {
  const store = openLogStore(':memory:')
  if (store.database === null) throw new Error('the in-memory log store failed to open')

  const logsDb = openLogsDatabase(store.database)

  return {
    context: { logsDb },
    store,
    close: async () => {
      await logsDb.destroy()
      store.close()
    },
  }
}

function seed(store: LogStore, lines: readonly NewLogLine[]): void {
  for (const line of lines) store.append(line)
  store.flushSync()
}

async function matchedMessages(
  context: LogsContext,
  filters: Parameters<typeof logRows>[1],
): Promise<(string | null)[]> {
  const rows = await logRows(context, filters, FULL_PAGE)

  return rows.map((row) => row.msg)
}

test('no filters read every stored line, newest first with the rowid tiebreak', async (t) => {
  const world = openLogsWorld()
  t.after(world.close)
  seed(world.store, [
    storedLogLine({ ts: '2026-08-24T12:00:00.000Z', msg: 'older' }),
    storedLogLine({ ts: '2026-08-24T12:00:01.000Z', msg: 'same millisecond, first' }),
    storedLogLine({ ts: '2026-08-24T12:00:01.000Z', msg: 'same millisecond, second' }),
  ])

  assert.deepEqual(await matchedMessages(world.context, {}), [
    'same millisecond, second',
    'same millisecond, first',
    'older',
  ])
})

test('each column filter narrows to the lines carrying its value', async (t) => {
  const world = openLogsWorld()
  t.after(world.close)
  seed(world.store, [
    storedLogLine({
      msg: 'wanted',
      level: 'warn',
      event: 'order.pay',
      phase: 'refused',
      requestId: 'req-wanted',
      txnId: 'txn_01ARZ3NDEKTSV4RRFFQ69G5FAV',
      sessionId: 'ses_01ARZ3NDEKTSV4RRFFQ69G5FAV',
      actorId: 'cus_01ARZ3NDEKTSV4RRFFQ69G5FAV',
    }),
    storedLogLine({ msg: 'other' }),
  ])

  assert.deepEqual(await matchedMessages(world.context, { level: 'warn' }), ['wanted'])
  assert.deepEqual(await matchedMessages(world.context, { event: 'order.pay' }), ['wanted'])
  assert.deepEqual(await matchedMessages(world.context, { phase: 'refused' }), ['wanted'])
  assert.deepEqual(await matchedMessages(world.context, { requestId: 'req-wanted' }), ['wanted'])
  assert.deepEqual(
    await matchedMessages(world.context, { txnId: 'txn_01ARZ3NDEKTSV4RRFFQ69G5FAV' }),
    ['wanted'],
  )
  assert.deepEqual(
    await matchedMessages(world.context, { sessionId: 'ses_01ARZ3NDEKTSV4RRFFQ69G5FAV' }),
    ['wanted'],
  )
  assert.deepEqual(
    await matchedMessages(world.context, { actorId: 'cus_01ARZ3NDEKTSV4RRFFQ69G5FAV' }),
    ['wanted'],
  )
})

test('the msg filter is a substring match with LIKE wildcards read literally', async (t) => {
  const world = openLogsWorld()
  t.after(world.close)
  seed(world.store, [
    storedLogLine({ msg: 'refunded 100% of the total' }),
    storedLogLine({ msg: 'refunded 100x of the total' }),
    storedLogLine({ msg: 'shipped' }),
  ])

  assert.deepEqual(await matchedMessages(world.context, { msg: 'refunded' }), [
    'refunded 100x of the total',
    'refunded 100% of the total',
  ])
  assert.deepEqual(await matchedMessages(world.context, { msg: '100%' }), [
    'refunded 100% of the total',
  ])
})

test('from and to bound the range, inclusively, by lexical ts', async (t) => {
  const world = openLogsWorld()
  t.after(world.close)
  seed(world.store, [
    storedLogLine({ ts: '2026-08-24T11:00:00.000Z', msg: 'before' }),
    storedLogLine({ ts: '2026-08-24T12:00:00.000Z', msg: 'inside' }),
    storedLogLine({ ts: '2026-08-24T13:00:00.000Z', msg: 'after' }),
  ])

  assert.deepEqual(
    await matchedMessages(world.context, {
      from: '2026-08-24T12:00:00.000Z',
      to: '2026-08-24T12:59:59.999Z',
    }),
    ['inside'],
  )
  assert.deepEqual(await matchedMessages(world.context, { from: '2026-08-24T12:00:00.000Z' }), [
    'after',
    'inside',
  ])
  assert.deepEqual(await matchedMessages(world.context, { to: '2026-08-24T12:00:00.000Z' }), [
    'inside',
    'before',
  ])
})

test('an attribute key naming a mirrored column matches that column', async (t) => {
  const world = openLogsWorld()
  t.after(world.close)
  seed(world.store, [
    storedLogLine({ msg: 'wanted', event: 'order.pay' }),
    storedLogLine({ msg: 'other' }),
  ])

  assert.deepEqual(
    await matchedMessages(world.context, { attribute: { key: 'event', value: 'order.pay' } }),
    ['wanted'],
  )
})

test('an attribute key with no value asks for existence, mirrored columns included', async (t) => {
  const world = openLogsWorld()
  t.after(world.close)
  seed(world.store, [
    storedLogLine({ msg: 'timed', durationMs: 12 }),
    storedLogLine({ msg: 'untimed' }),
    storedLogLine({ msg: 'refunding', data: JSON.stringify({ refund_id: 'rfd-1' }) }),
  ])

  assert.deepEqual(
    await matchedMessages(world.context, { attribute: { key: 'duration_ms' } }),
    ['timed'],
  )
  assert.deepEqual(
    await matchedMessages(world.context, { attribute: { key: 'data.refund_id' } }),
    ['refunding'],
  )
})

test('a dotted attribute key reaches into data through raw, segments quoted', async (t) => {
  const world = openLogsWorld()
  t.after(world.close)
  seed(world.store, [
    storedLogLine({
      msg: 'wanted',
      data: JSON.stringify({ order: { id: 'ord_01ARZ3NDEKTSV4RRFFQ69G5FAV' } }),
    }),
    storedLogLine({ msg: 'other', data: JSON.stringify({ order: { id: 'ord_other' } }) }),
  ])

  assert.deepEqual(
    await matchedMessages(world.context, {
      attribute: { key: 'data.order.id', value: 'ord_01ARZ3NDEKTSV4RRFFQ69G5FAV' },
    }),
    ['wanted'],
  )
})

test('a numeric-looking value matches a JSON number and a string that looks like one', async (t) => {
  const world = openLogsWorld()
  t.after(world.close)
  seed(world.store, [
    storedLogLine({ msg: 'number', data: JSON.stringify({ total_cents: 45_000 }) }),
    storedLogLine({ msg: 'string', data: JSON.stringify({ total_cents: '45000' }) }),
    storedLogLine({ msg: 'other', data: JSON.stringify({ total_cents: 9 }) }),
  ])

  assert.deepEqual(
    await matchedMessages(world.context, {
      attribute: { key: 'data.total_cents', value: '45000' },
    }),
    ['string', 'number'],
  )
})

test('a JSON boolean answers the numeric side as 1 or 0', async (t) => {
  const world = openLogsWorld()
  t.after(world.close)
  seed(world.store, [
    storedLogLine({ msg: 'flagged', data: JSON.stringify({ flagged: true }) }),
    storedLogLine({ msg: 'unflagged', data: JSON.stringify({ flagged: false }) }),
  ])

  assert.deepEqual(
    await matchedMessages(world.context, { attribute: { key: 'data.flagged', value: '1' } }),
    ['flagged'],
  )
})

test('a top-level extra like pid is reachable, one code path with data.*', async (t) => {
  const world = openLogsWorld()
  t.after(world.close)
  seed(world.store, [
    storedLogLine({ msg: 'wanted', raw: JSON.stringify({ ts: '2026-08-24T12:00:00.000Z', msg: 'wanted', pid: 42 }) }),
    storedLogLine({ msg: 'other' }),
  ])

  assert.deepEqual(
    await matchedMessages(world.context, { attribute: { key: 'pid', value: '42' } }),
    ['wanted'],
  )
})

test('countLogRows counts every match, independent of the page', async (t) => {
  const world = openLogsWorld()
  t.after(world.close)
  seed(world.store, [
    storedLogLine({ level: 'info' }),
    storedLogLine({ level: 'info' }),
    storedLogLine({ level: 'error' }),
  ])

  assert.equal(await countLogRows(world.context), 3)
  assert.equal(await countLogRows(world.context, { level: 'info' }), 2)
})

test('the page offset and limit slice the ordered rows', async (t) => {
  const world = openLogsWorld()
  t.after(world.close)
  seed(world.store, [
    storedLogLine({ ts: '2026-08-24T12:00:00.000Z', msg: 'oldest' }),
    storedLogLine({ ts: '2026-08-24T12:00:01.000Z', msg: 'middle' }),
    storedLogLine({ ts: '2026-08-24T12:00:02.000Z', msg: 'newest' }),
  ])

  const firstPage = await logRows(world.context, {}, { offset: 0, limit: 2 })
  assert.deepEqual(firstPage.map((row) => row.msg), ['newest', 'middle'])

  const secondPage = await logRows(world.context, {}, { offset: 2, limit: 2 })
  assert.deepEqual(secondPage.map((row) => row.msg), ['oldest'])
})

test('logLevelTallies answers every level, counts the other filters, and ignores the level filter', async (t) => {
  const world = openLogsWorld()
  t.after(world.close)
  seed(world.store, [
    storedLogLine({ level: 'error', event: 'order.pay' }),
    storedLogLine({ level: 'info', event: 'order.pay' }),
    storedLogLine({ level: 'info', event: 'order.pay' }),
    storedLogLine({ level: 'info', event: 'listing.view' }),
  ])

  const tallies = await logLevelTallies(world.context, { level: 'error', event: 'order.pay' })

  assert.deepEqual(tallies, [
    { key: 'debug', count: 0 },
    { key: 'info', count: 2 },
    { key: 'warn', count: 0 },
    { key: 'error', count: 1 },
  ])
})

test('a line with a level outside the vocabulary counts toward no tile', async (t) => {
  const world = openLogsWorld()
  t.after(world.close)
  seed(world.store, [storedLogLine({ level: 'trace' }), storedLogLine({ level: null })])

  const tallies = await logLevelTallies(world.context)

  assert.deepEqual(tallies.map((tally) => tally.count), [0, 0, 0, 0])
})

test('requestStoryRows reads one request in the order it happened', async (t) => {
  const world = openLogsWorld()
  t.after(world.close)
  seed(world.store, [
    storedLogLine({ ts: '2026-08-24T12:00:02.000Z', msg: 'closed', requestId: 'req-story' }),
    storedLogLine({ ts: '2026-08-24T12:00:00.000Z', msg: 'opened', requestId: 'req-story' }),
    storedLogLine({ ts: '2026-08-24T12:00:01.000Z', msg: 'worked', requestId: 'req-story' }),
    storedLogLine({ ts: '2026-08-24T12:00:01.000Z', msg: 'elsewhere', requestId: 'req-other' }),
  ])

  const rows = await requestStoryRows(world.context, 'req-story')

  assert.deepEqual(rows.map((row) => row.msg), ['opened', 'worked', 'closed'])
})

test('the story stops at the cap', async (t) => {
  const world = openLogsWorld()
  t.after(world.close)
  seed(
    world.store,
    Array.from({ length: STORY_LINE_CAP + 1 }, (_, index) =>
      storedLogLine({
        ts: `2026-08-24T12:00:00.${String(index % 1000).padStart(3, '0')}Z`,
        requestId: 'req-long',
      }),
    ),
  )

  const rows = await requestStoryRows(world.context, 'req-long')

  assert.equal(rows.length, STORY_LINE_CAP)
  assert.equal(await countLogRows(world.context, { requestId: 'req-long' }), STORY_LINE_CAP + 1)
})

test('a domain filter narrows to lines whose request visited that site', async (t) => {
  const world = openLogsWorld()
  t.after(world.close)
  seed(world.store, [
    storedLogLine({
      msg: 'admin will',
      event: 'http.request',
      phase: 'will',
      requestId: 'req-admin',
      data: JSON.stringify({ method: 'GET', path: '/admin/orders' }),
    }),
    storedLogLine({ msg: 'admin step', requestId: 'req-admin' }),
    storedLogLine({
      msg: 'seller will',
      event: 'http.request',
      phase: 'will',
      requestId: 'req-seller',
      data: JSON.stringify({ method: 'GET', path: '/seller/listings' }),
    }),
    storedLogLine({
      msg: 'shop will',
      event: 'http.request',
      phase: 'will',
      requestId: 'req-shop',
      data: JSON.stringify({ method: 'GET', path: '/checkout' }),
    }),
  ])

  assert.deepEqual(await matchedMessages(world.context, { domain: 'admin' }), [
    'admin step',
    'admin will',
  ])
  assert.deepEqual(await matchedMessages(world.context, { domain: 'seller' }), ['seller will'])
  assert.deepEqual(await matchedMessages(world.context, { domain: 'shop' }), ['shop will'])
})

test('the shop domain excludes the health probe and the per-site events stream', async (t) => {
  const world = openLogsWorld()
  t.after(world.close)
  seed(world.store, [
    storedLogLine({
      msg: 'checkout',
      event: 'http.request',
      phase: 'will',
      requestId: 'req-page',
      data: JSON.stringify({ method: 'GET', path: '/checkout' }),
    }),
    storedLogLine({
      msg: 'health',
      event: 'http.request',
      phase: 'will',
      requestId: 'req-health',
      data: JSON.stringify({ method: 'GET', path: '/health' }),
    }),
    storedLogLine({
      msg: 'events',
      event: 'http.request',
      phase: 'will',
      requestId: 'req-events',
      data: JSON.stringify({ method: 'GET', path: '/events' }),
    }),
  ])

  assert.deepEqual(await matchedMessages(world.context, { domain: 'shop' }), ['checkout'])
})

test('a line with no request_id matches no domain filter', async (t) => {
  const world = openLogsWorld()
  t.after(world.close)
  seed(world.store, [
    storedLogLine({ msg: 'cli run', requestId: null, event: 'migrate.run', phase: 'did' }),
  ])

  assert.deepEqual(await matchedMessages(world.context, { domain: 'shop' }), [])
  assert.deepEqual(await matchedMessages(world.context, { domain: 'admin' }), [])
  assert.deepEqual(await matchedMessages(world.context, { domain: 'seller' }), [])
})

test('a domain match is a path segment boundary, not a bare prefix', async (t) => {
  const world = openLogsWorld()
  t.after(world.close)
  seed(world.store, [
    storedLogLine({
      msg: 'sellers directory',
      event: 'http.request',
      phase: 'will',
      requestId: 'req-sellers',
      data: JSON.stringify({ method: 'GET', path: '/sellers/whatever' }),
    }),
    storedLogLine({
      msg: 'administrator page',
      event: 'http.request',
      phase: 'will',
      requestId: 'req-administrator',
      data: JSON.stringify({ method: 'GET', path: '/administrator' }),
    }),
  ])

  assert.deepEqual(await matchedMessages(world.context, { domain: 'seller' }), [])
  assert.deepEqual(await matchedMessages(world.context, { domain: 'admin' }), [])
  assert.deepEqual(await matchedMessages(world.context, { domain: 'shop' }), [
    'administrator page',
    'sellers directory',
  ])
})

test('grouping collapses one request into one row summarizing the will/did pair', async (t) => {
  const world = openLogsWorld()
  t.after(world.close)
  seed(world.store, [
    storedLogLine({
      ts: '2026-08-24T12:00:00.000Z',
      msg: '🎬 GET /checkout',
      event: 'http.request',
      phase: 'will',
      requestId: 'req-group',
      data: JSON.stringify({ method: 'GET', path: '/checkout' }),
    }),
    storedLogLine({
      ts: '2026-08-24T12:00:01.000Z',
      msg: 'order placed',
      event: 'order.place',
      phase: 'did',
      requestId: 'req-group',
    }),
    storedLogLine({
      ts: '2026-08-24T12:00:02.000Z',
      msg: '🟢 GET /checkout 200',
      event: 'http.request',
      phase: 'did',
      requestId: 'req-group',
      durationMs: 42,
      data: JSON.stringify({ status: 200 }),
    }),
  ])

  const groups = await logRequestGroups(world.context, {}, FULL_PAGE)

  assert.equal(groups.length, 1)
  const [group] = groups
  if (group === undefined) throw new Error('expected a group')
  assert.equal(group.key, 'req-group')
  assert.equal(group.kind, 'request')
  assert.equal(group.lineCount, 3)
  assert.equal(group.method, 'GET')
  assert.equal(group.path, '/checkout')
  assert.equal(group.status, 200)
  assert.equal(group.durationMs, 42)
  assert.equal(group.level, 'info')
  assert.equal(group.msg, '🟢 GET /checkout 200')
  assert.deepEqual(group.lines.map((line) => line.msg), [
    '🎬 GET /checkout',
    'order placed',
    '🟢 GET /checkout 200',
  ])
})

test('an in-flight request with no close yet summarizes from the will line alone', async (t) => {
  const world = openLogsWorld()
  t.after(world.close)
  seed(world.store, [
    storedLogLine({
      msg: '🎬 GET /checkout',
      event: 'http.request',
      phase: 'will',
      requestId: 'req-in-flight',
      data: JSON.stringify({ method: 'GET', path: '/checkout' }),
    }),
  ])

  const groups = await logRequestGroups(world.context, {}, FULL_PAGE)

  assert.equal(groups.length, 1)
  const [group] = groups
  if (group === undefined) throw new Error('expected a group')
  assert.equal(group.method, 'GET')
  assert.equal(group.path, '/checkout')
  assert.equal(group.status, null)
  assert.equal(group.durationMs, null)
  assert.equal(group.level, null)
  assert.equal(group.msg, '🎬 GET /checkout')
})

test('a failed root close still summarizes the group', async (t) => {
  const world = openLogsWorld()
  t.after(world.close)
  seed(world.store, [
    storedLogLine({
      msg: '🎬 GET /checkout',
      event: 'http.request',
      phase: 'will',
      requestId: 'req-failed',
      data: JSON.stringify({ method: 'GET', path: '/checkout' }),
    }),
    storedLogLine({
      msg: '🔴 GET /checkout 500',
      event: 'http.request',
      phase: 'failed',
      requestId: 'req-failed',
      level: 'error',
      durationMs: 9,
      data: JSON.stringify({ status: 500 }),
    }),
  ])

  const groups = await logRequestGroups(world.context, {}, FULL_PAGE)

  assert.equal(groups.length, 1)
  const [group] = groups
  if (group === undefined) throw new Error('expected a group')
  assert.equal(group.status, 500)
  assert.equal(group.durationMs, 9)
  assert.equal(group.level, 'error')
  assert.equal(group.msg, '🔴 GET /checkout 500')
})

test('a line with no request_id becomes its own group', async (t) => {
  const world = openLogsWorld()
  t.after(world.close)
  seed(world.store, [
    storedLogLine({ msg: 'first cli line', requestId: null, event: 'migrate.run', phase: 'did' }),
    storedLogLine({ msg: 'second cli line', requestId: null, event: 'seed.run', phase: 'did' }),
  ])

  const groups = await logRequestGroups(world.context, {}, FULL_PAGE)

  assert.equal(groups.length, 2)
  assert.ok(groups.every((group) => group.kind === 'line' && group.lineCount === 1))
  assert.deepEqual(
    groups.map((group) => group.msg),
    ['second cli line', 'first cli line'],
  )
})

test('groups sort newest first by their most recent line', async (t) => {
  const world = openLogsWorld()
  t.after(world.close)
  seed(world.store, [
    storedLogLine({ ts: '2026-08-24T12:00:00.000Z', requestId: 'req-old', msg: 'old' }),
    storedLogLine({ ts: '2026-08-24T12:00:05.000Z', requestId: 'req-new', msg: 'new' }),
    storedLogLine({ ts: '2026-08-24T12:00:01.000Z', requestId: 'req-old', msg: 'old, updated' }),
  ])

  const groups = await logRequestGroups(world.context, {}, FULL_PAGE)

  assert.deepEqual(
    groups.map((group) => group.key),
    ['req-new', 'req-old'],
  )
})

test('the page offset and limit slice the ordered groups', async (t) => {
  const world = openLogsWorld()
  t.after(world.close)
  seed(world.store, [
    storedLogLine({ ts: '2026-08-24T12:00:00.000Z', requestId: 'req-a' }),
    storedLogLine({ ts: '2026-08-24T12:00:01.000Z', requestId: 'req-b' }),
    storedLogLine({ ts: '2026-08-24T12:00:02.000Z', requestId: 'req-c' }),
  ])

  const firstPage = await logRequestGroups(world.context, {}, { offset: 0, limit: 2 })
  assert.deepEqual(
    firstPage.map((group) => group.key),
    ['req-c', 'req-b'],
  )

  const secondPage = await logRequestGroups(world.context, {}, { offset: 2, limit: 2 })
  assert.deepEqual(
    secondPage.map((group) => group.key),
    ['req-a'],
  )
})

test('countLogGroups counts distinct requests, and orphan lines singly', async (t) => {
  const world = openLogsWorld()
  t.after(world.close)
  seed(world.store, [
    storedLogLine({ requestId: 'req-a', msg: 'one' }),
    storedLogLine({ requestId: 'req-a', msg: 'two' }),
    storedLogLine({ requestId: null, msg: 'cli' }),
  ])

  assert.equal(await countLogGroups(world.context), 2)
})

test('a filter narrows which groups appear; the opened group still shows the whole request', async (t) => {
  const world = openLogsWorld()
  t.after(world.close)
  seed(world.store, [
    storedLogLine({ requestId: 'req-mixed', level: 'info', msg: 'quiet step' }),
    storedLogLine({ requestId: 'req-mixed', level: 'error', msg: 'the error line' }),
    storedLogLine({ requestId: 'req-quiet', level: 'info', msg: 'no errors here' }),
  ])

  const groups = await logRequestGroups(world.context, { level: 'error' }, FULL_PAGE)

  assert.deepEqual(
    groups.map((group) => group.key),
    ['req-mixed'],
  )
  const [group] = groups
  if (group === undefined) throw new Error('expected a group')
  assert.deepEqual(group.lines.map((line) => line.msg), ['quiet step', 'the error line'])
})

test('a root line whose data never parses summarizes without throwing', async (t) => {
  const world = openLogsWorld()
  t.after(world.close)
  seed(world.store, [
    storedLogLine({
      event: 'http.request',
      phase: 'will',
      requestId: 'req-malformed',
      data: 'not json',
      raw: '{}',
    }),
  ])

  const groups = await logRequestGroups(world.context, {}, FULL_PAGE)

  assert.equal(groups.length, 1)
  const [group] = groups
  if (group === undefined) throw new Error('expected a group')
  assert.equal(group.method, null)
  assert.equal(group.path, null)
})

test('a health-check request is hidden by default, pair and every line between', async (t) => {
  const world = openLogsWorld()
  t.after(world.close)
  seed(world.store, [
    storedLogLine({
      msg: '🎬 GET /health',
      event: 'http.request',
      phase: 'will',
      requestId: 'req-health',
      data: JSON.stringify({ method: 'GET', path: '/health' }),
    }),
    storedLogLine({ msg: 'health step', requestId: 'req-health' }),
    storedLogLine({
      msg: '🟢 GET /health 200',
      event: 'http.request',
      phase: 'did',
      requestId: 'req-health',
      durationMs: 1,
      data: JSON.stringify({ status: 200 }),
    }),
    storedLogLine({ msg: 'real traffic', requestId: 'req-shop' }),
  ])

  assert.deepEqual(await matchedMessages(world.context, {}), ['real traffic'])
})

test('hideHealth: false includes the health-check lines again', async (t) => {
  const world = openLogsWorld()
  t.after(world.close)
  seed(world.store, [
    storedLogLine({
      msg: '🎬 GET /health',
      event: 'http.request',
      phase: 'will',
      requestId: 'req-health',
      data: JSON.stringify({ method: 'GET', path: '/health' }),
    }),
    storedLogLine({ msg: 'real traffic', requestId: 'req-shop' }),
  ])

  assert.deepEqual(await matchedMessages(world.context, { hideHealth: false }), [
    'real traffic',
    '🎬 GET /health',
  ])
})

test('a request whose path merely starts with /health is not hidden — an exact match, not a prefix', async (t) => {
  const world = openLogsWorld()
  t.after(world.close)
  seed(world.store, [
    storedLogLine({
      msg: 'healthcheck page',
      event: 'http.request',
      phase: 'will',
      requestId: 'req-healthcheck',
      data: JSON.stringify({ method: 'GET', path: '/healthcheck' }),
    }),
    storedLogLine({
      msg: 'health sub-path',
      event: 'http.request',
      phase: 'will',
      requestId: 'req-health-sub',
      data: JSON.stringify({ method: 'GET', path: '/health/x' }),
    }),
  ])

  assert.deepEqual(await matchedMessages(world.context, {}), [
    'health sub-path',
    'healthcheck page',
  ])
})

test('a line with no request_id is never treated as a health check', async (t) => {
  const world = openLogsWorld()
  t.after(world.close)
  seed(world.store, [
    storedLogLine({ msg: 'cli run', requestId: null, event: 'migrate.run', phase: 'did' }),
  ])

  assert.deepEqual(await matchedMessages(world.context, {}), ['cli run'])
})

test('domain=shop already excludes health, and hideHealth: false does not undo it', async (t) => {
  const world = openLogsWorld()
  t.after(world.close)
  seed(world.store, [
    storedLogLine({
      msg: 'health',
      event: 'http.request',
      phase: 'will',
      requestId: 'req-health',
      data: JSON.stringify({ method: 'GET', path: '/health' }),
    }),
    storedLogLine({
      msg: 'checkout',
      event: 'http.request',
      phase: 'will',
      requestId: 'req-page',
      data: JSON.stringify({ method: 'GET', path: '/checkout' }),
    }),
  ])

  assert.deepEqual(await matchedMessages(world.context, { domain: 'shop', hideHealth: false }), [
    'checkout',
  ])
})

test('logLevelTallies respects the hidden-by-default rule so counts match the visible list', async (t) => {
  const world = openLogsWorld()
  t.after(world.close)
  seed(world.store, [
    storedLogLine({
      level: 'error',
      event: 'http.request',
      phase: 'will',
      requestId: 'req-health',
      data: JSON.stringify({ method: 'GET', path: '/health' }),
    }),
    storedLogLine({ level: 'info', requestId: 'req-shop' }),
  ])

  const tallies = await logLevelTallies(world.context)

  assert.deepEqual(tallies, [
    { key: 'debug', count: 0 },
    { key: 'info', count: 1 },
    { key: 'warn', count: 0 },
    { key: 'error', count: 0 },
  ])
})

test('grouping also hides the health-check request by default', async (t) => {
  const world = openLogsWorld()
  t.after(world.close)
  seed(world.store, [
    storedLogLine({
      event: 'http.request',
      phase: 'will',
      requestId: 'req-health',
      data: JSON.stringify({ method: 'GET', path: '/health' }),
    }),
    storedLogLine({ requestId: 'req-shop' }),
  ])

  const groups = await logRequestGroups(world.context, {}, FULL_PAGE)

  assert.deepEqual(
    groups.map((group) => group.key),
    ['req-shop'],
  )
})

test('domain and group compose: only the matching request appears, in full', async (t) => {
  const world = openLogsWorld()
  t.after(world.close)
  seed(world.store, [
    storedLogLine({
      requestId: 'req-admin',
      event: 'http.request',
      phase: 'will',
      data: JSON.stringify({ method: 'GET', path: '/admin/orders' }),
    }),
    storedLogLine({ requestId: 'req-admin', msg: 'admin step' }),
    storedLogLine({
      requestId: 'req-shop',
      event: 'http.request',
      phase: 'will',
      data: JSON.stringify({ method: 'GET', path: '/checkout' }),
    }),
  ])

  const groups = await logRequestGroups(world.context, { domain: 'admin' }, FULL_PAGE)

  assert.deepEqual(
    groups.map((group) => group.key),
    ['req-admin'],
  )
})
