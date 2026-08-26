import { test } from 'node:test'
import assert from 'node:assert/strict'
import { logLine, logStep, SILENT_LOG, tellStory, type AppLogger, type LogData } from './log-story.ts'
import { BrokenContractError, MissingDataError } from './core/defect.ts'
import { refused, type Refusal } from './core/refusal.ts'

type Written = { level: string; payload: Record<string, unknown>; msg: string }

/** A logger that keeps what it was told, so a test reads the lines back. */
function recordingLog(written: Written[], bindings: LogData = {}): AppLogger {
  const record =
    (level: string) =>
    (payload: object, msg: string): void => {
      written.push({ level, payload: { ...bindings, ...payload }, msg })
    }

  return {
    debug: record('debug'),
    info: record('info'),
    warn: record('warn'),
    error: record('error'),
    child: (extra) => recordingLog(written, { ...bindings, ...extra }),
  }
}

test('a story that succeeds is one will line and one did line', async () => {
  const written: Written[] = []

  const result = await tellStory(
    recordingLog(written),
    {
      event: 'order.place',
      will: { msg: 'placing an order from the cart', data: { cart_id: 'crt_1' } },
      ended: (order: string) => ({ phase: 'did', msg: 'placed the order', data: { order_id: order } }),
    },
    async () => 'ord_1',
  )

  assert.equal(result, 'ord_1')
  assert.equal(written.length, 2)
  assert.deepEqual(written[0], {
    level: 'info',
    payload: { event: 'order.place', phase: 'will', data: { cart_id: 'crt_1' } },
    msg: 'placing an order from the cart',
  })
  assert.equal(written[1]?.payload.phase, 'did')
  assert.deepEqual(written[1]?.payload.data, { order_id: 'ord_1' })
  assert.equal(typeof written[1]?.payload.duration_ms, 'number')
})

test('a line with no facts behind it carries no data key at all', async () => {
  const written: Written[] = []

  await tellStory(
    recordingLog(written),
    {
      event: 'app.boot',
      will: { msg: 'starting' },
      ended: () => ({ phase: 'did', msg: 'listening' }),
    },
    async () => undefined,
  )

  assert.deepEqual(Object.keys(written[0]?.payload ?? {}), ['event', 'phase'])
  assert.deepEqual(Object.keys(written[1]?.payload ?? {}).sort(), ['duration_ms', 'event', 'phase'])
})

test('an outcome the story calls a refusal is refused, not did', async () => {
  const written: Written[] = []

  await tellStory(
    recordingLog(written),
    {
      event: 'order.pay',
      will: { msg: 'charging the card' },
      ended: () => ({ phase: 'refused', msg: 'the card was declined', data: { decline_reason: 'do_not_honor' } }),
    },
    async () => undefined,
  )

  assert.equal(written[1]?.level, 'info')
  assert.equal(written[1]?.payload.phase, 'refused')
})

test('a thrown error carrying a reason and data is failed at error — a throw is a defect, always', async () => {
  const written: Written[] = []

  await assert.rejects(
    tellStory(
      recordingLog(written),
      {
        event: 'listing.transition',
        will: { msg: 'moving the listing to for_sale' },
        ended: () => ({ phase: 'did', msg: 'moved the listing' }),
      },
      async () => {
        throw new MissingDataError('stale_status', 'A listing cannot move from archived to for_sale.', {
          listing_id: 'lst_1',
        })
      },
    ),
    MissingDataError,
  )

  assert.equal(written[1]?.level, 'error')
  assert.equal(written[1]?.payload.phase, 'failed')
  const error = written[1]?.payload.error as Record<string, unknown>
  assert.equal(error.type, 'MissingDataError')
  assert.equal(error.reason, 'stale_status')
  assert.deepEqual(error.data, { listing_id: 'lst_1' })
})

test('an exception nobody expected is failed at error, with the type, message, and stack', async () => {
  const written: Written[] = []

  await assert.rejects(
    tellStory(
      recordingLog(written),
      {
        event: 'order.place',
        will: { msg: 'placing an order from the cart' },
        ended: () => ({ phase: 'did', msg: 'placed the order' }),
      },
      async () => {
        throw new Error('the database went away')
      },
    ),
    /the database went away/,
  )

  assert.equal(written[1]?.level, 'error')
  assert.equal(written[1]?.payload.phase, 'failed')
  assert.equal(written[1]?.msg, '🛑 the order.place action failed')
  const error = written[1]?.payload.error as Record<string, unknown>
  assert.equal(error.type, 'Error')
  assert.equal(error.message, 'the database went away')
  assert.equal(typeof error.stack, 'string')
  assert.equal(typeof written[1]?.payload.duration_ms, 'number')
})

test('a thrown defect is failed at error, with the reason and data it carried', async () => {
  const written: Written[] = []

  await assert.rejects(
    tellStory(
      recordingLog(written),
      {
        event: 'order.place',
        will: { msg: 'placing an order from the cart' },
        ended: () => ({ phase: 'did', msg: 'placed the order' }),
      },
      async () => {
        throw new MissingDataError('row_not_found', 'No cart matches crt_1.', { cart_id: 'crt_1' })
      },
    ),
    MissingDataError,
  )

  assert.equal(written[1]?.level, 'error')
  const error = written[1]?.payload.error as Record<string, unknown>
  assert.equal(error.type, 'MissingDataError')
  assert.equal(error.reason, 'row_not_found')
  assert.equal(error.message, 'No cart matches crt_1.')
  assert.deepEqual(error.data, { cart_id: 'crt_1' })
  assert.equal(typeof error.stack, 'string')
})

test('a root story prefixes its will line with 🎬 and its did line with 🟢', async () => {
  const written: Written[] = []

  await tellStory(
    recordingLog(written),
    {
      event: 'migrate.run',
      root: true,
      will: { msg: 'migrating db.sqlite3' },
      ended: () => ({ phase: 'did', msg: 'db.sqlite3 is up to date' }),
    },
    async () => undefined,
  )

  assert.equal(written[0]?.msg, '🎬 migrating db.sqlite3')
  assert.equal(written[1]?.msg, '🟢 db.sqlite3 is up to date')
})

test('a root story that fails prefixes the failed line with ❌', async () => {
  const written: Written[] = []

  await assert.rejects(
    tellStory(
      recordingLog(written),
      {
        event: 'seed.run',
        root: true,
        will: { msg: 'seeding db.sqlite3' },
        ended: () => ({ phase: 'did', msg: 'seeded' }),
      },
      async () => {
        throw new Error('the database went away')
      },
    ),
    /the database went away/,
  )

  assert.equal(written[1]?.msg, '❌ the seed.run action failed')
})

test('a story written at debug keeps its whole story out of the way', async () => {
  const written: Written[] = []

  await tellStory(
    recordingLog(written),
    {
      event: 'listing.view',
      level: 'debug',
      will: { msg: 'recording a view of the listing' },
      ended: () => ({ phase: 'refused', msg: 'already viewed this hour' }),
    },
    async () => undefined,
  )

  assert.deepEqual(
    written.map((line) => line.level),
    ['debug', 'debug'],
  )
})

test('a step inside a unit of work is a doing line', () => {
  const written: Written[] = []

  logStep(recordingLog(written), 'notification.deliver', { msg: 'wrote a.eml', data: { file: 'a.eml' } })
  logStep(recordingLog(written), 'ledger.write', { msg: 'held 100' }, 'debug')

  assert.equal(written[0]?.payload.phase, 'doing')
  assert.equal(written[0]?.level, 'info')
  assert.equal(written[1]?.level, 'debug')
})

test('a line written on its own carries the event, the phase, and its facts', () => {
  const written: Written[] = []

  logLine(recordingLog(written), 'info', 'migrate.apply', 'did', {
    msg: 'Success 20260822000001-enable-write-ahead-logging',
    data: { status: 'Success' },
  })

  assert.deepEqual(written[0]?.payload, {
    event: 'migrate.apply',
    phase: 'did',
    data: { status: 'Success' },
  })
})

test('a child logger carries its bindings onto every line under it', async () => {
  const written: Written[] = []
  const log = recordingLog(written).child({ txn_id: 'txn_1' })

  await tellStory(
    log,
    {
      event: 'cart.add',
      will: { msg: 'adding the listing to the cart' },
      ended: () => ({ phase: 'did', msg: 'added the listing to the cart' }),
    },
    async () => undefined,
  )

  assert.deepEqual(
    written.map((line) => line.payload.txn_id),
    ['txn_1', 'txn_1'],
  )
})

test('the silent logger writes nowhere and its children write nowhere either', async () => {
  SILENT_LOG.debug({}, 'nothing')
  SILENT_LOG.info({}, 'nothing')
  SILENT_LOG.warn({}, 'nothing')
  SILENT_LOG.error({}, 'nothing')

  assert.equal(SILENT_LOG.child({ txn_id: 'txn_1' }), SILENT_LOG)

  const result = await tellStory(
    SILENT_LOG,
    {
      event: 'cart.remove',
      will: { msg: 'taking the listing out of the cart' },
      ended: () => ({ phase: 'did', msg: 'took the listing out of the cart' }),
    },
    async () => 'done',
  )

  assert.equal(result, 'done')
})

type ShipResult =
  | { outcome: 'shipped'; id: string }
  | Refusal<'illegal_transition', { fulfillment_id: string; status_from: string; status_to: string }>

test('the machinery writes the refused line from a returned refusal', async () => {
  const written: Written[] = []

  await tellStory(
    recordingLog(written),
    {
      event: 'fulfillment.ship',
      will: { msg: 'moving the fulfillment to shipped' },
      refusedMsg: 'the fulfillment cannot move to shipped',
      ended: () => {
        assert.fail('ended must not run for a returned refusal')
      },
    },
    async (): Promise<ShipResult> =>
      refused('illegal_transition', { fulfillment_id: 'flm_1', status_from: 'delivered', status_to: 'shipped' }),
  )

  assert.equal(written[1]?.level, 'info')
  assert.equal(written[1]?.payload.phase, 'refused')
  assert.equal(written[1]?.msg, '⚠️ the fulfillment cannot move to shipped')
  assert.deepEqual(written[1]?.payload.data, {
    reason: 'illegal_transition',
    fulfillment_id: 'flm_1',
    status_from: 'delivered',
    status_to: 'shipped',
  })
  assert.deepEqual(Object.keys(written[1]?.payload.data as object), [
    'reason',
    'fulfillment_id',
    'status_from',
    'status_to',
  ])
  assert.equal(typeof written[1]?.payload.duration_ms, 'number')
})

test('a refusal with no facts still names its reason', async () => {
  const written: Written[] = []

  await tellStory(
    recordingLog(written),
    {
      event: 'magic_link.consume',
      will: { msg: 'signing in with a magic link' },
      refusedMsg: 'the magic link is not recognized',
      ended: () => {
        assert.fail('ended must not run for a returned refusal')
      },
    },
    async (): Promise<{ outcome: 'signed-in' } | Refusal<'unknown_token'>> => refused('unknown_token'),
  )

  assert.deepEqual(written[1]?.payload.data, { reason: 'unknown_token' })
})

test('a success result still reaches ended when the story carries a refusal sentence', async () => {
  const written: Written[] = []

  await tellStory(
    recordingLog(written),
    {
      event: 'fulfillment.ship',
      will: { msg: 'moving the fulfillment to shipped' },
      refusedMsg: 'the fulfillment cannot move to shipped',
      ended: (result) => ({ phase: 'did', msg: 'shipped the fulfillment', data: { id: result.id } }),
    },
    async (): Promise<ShipResult> => ({ outcome: 'shipped', id: 'flm_1' }),
  )

  assert.equal(written[1]?.payload.phase, 'did')
  assert.deepEqual(written[1]?.payload.data, { id: 'flm_1' })
})

test('a null result is an outcome, not a refusal', async () => {
  const written: Written[] = []

  await tellStory(
    recordingLog(written),
    {
      event: 'listing.view',
      will: { msg: 'recording a view of the listing' },
      ended: (result: string | null) =>
        result === null
          ? { phase: 'refused', msg: 'already viewed this hour' }
          : { phase: 'did', msg: 'recorded the view', data: { view_id: result } },
    },
    async (): Promise<string | null> => null,
  )

  assert.equal(written[1]?.payload.phase, 'refused')
  assert.equal(written[1]?.msg, '⚠️ already viewed this hour')
})

test('a refusal that ends a story with no sentence is a defect', async () => {
  const written: Written[] = []

  await assert.rejects(
    tellStory(
      recordingLog(written),
      {
        event: 'order.place',
        will: { msg: 'placing an order from the cart' },
        ended: (result: { outcome: string; reason: string }) => ({
          phase: 'did',
          msg: 'placed the order',
          data: { outcome: result.outcome },
        }),
      },
      async (): Promise<{ outcome: string; reason: string }> => ({ outcome: 'refused', reason: 'x' }),
    ),
    BrokenContractError,
  )

  assert.equal(written[1]?.level, 'error')
  assert.equal(written[1]?.payload.phase, 'failed')
})
