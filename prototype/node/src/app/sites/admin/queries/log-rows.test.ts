import { test } from 'node:test'
import assert from 'node:assert/strict'
import { openLogsDatabase } from '../../../db/database.ts'
import type { NewLogLine } from '../../../db/logs-schema.ts'
import { openLogStore, type LogStore } from '../../../log-store.ts'
import { storedLogLine } from '../../../test/log-lines.ts'
import {
  countLogRows,
  logLevelTallies,
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
