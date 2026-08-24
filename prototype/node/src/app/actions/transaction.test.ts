import { test } from 'node:test'
import assert from 'node:assert/strict'
import { newId } from '../ids.ts'
import { buildTestApp } from '../test/build-test-app.ts'
import type { AppLogger, LogData } from '../log-story.ts'
import { runInTransaction } from './transaction.ts'

test('it opens a transaction when the caller has none', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)

  const wasTransaction = await runInTransaction(testApp, async ({ db }) => db.isTransaction)

  assert.equal(testApp.db.isTransaction, false)
  assert.equal(wasTransaction, true)
})

test("it joins the caller's transaction rather than opening a second", async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)

  const joined = await runInTransaction(testApp, async (outer) =>
    runInTransaction(outer, async (inner) => inner.db === outer.db),
  )

  assert.equal(joined, true)
})

test('a throw inside rolls back every write the work made', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)

  await assert.rejects(
    runInTransaction(testApp, async ({ db }) => {
      await db
        .insertInto('sellers')
        .values({
          id: newId('sel', new Date()),
          email: 'artist@example.com',
          name: null,
          shopName: null,
          emailVerifiedAt: null,
          createdAt: '2026-08-24T12:00:00.000Z',
        })
        .execute()

      throw new Error('no')
    }),
  )

  assert.equal((await testApp.db.selectFrom('sellers').selectAll().execute()).length, 0)
})

test('opening a unit of work opens a txn_id every line inside it carries', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const written: Record<string, unknown>[] = []

  await runInTransaction({ ...testApp, log: recordingLog(written) }, async (transacted) => {
    transacted.log?.info({ event: 'ledger.write', phase: 'did' }, 'held 100')

    await runInTransaction(transacted, async (joined) => {
      joined.log?.info({ event: 'notification.write', phase: 'did' }, 'filed a notification')
    })
  })

  assert.equal(written.length, 2)
  assert.match(String(written[0]?.txn_id), /^txn_[0-9A-HJKMNP-TV-Z]{26}$/)
  assert.equal(written[1]?.txn_id, written[0]?.txn_id)
})

test('two units of work get two ids', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const written: Record<string, unknown>[] = []
  const log = recordingLog(written)

  await runInTransaction({ ...testApp, log }, async ({ log: inner }) => {
    inner?.info({ event: 'cart.add', phase: 'did' }, 'added')
  })
  await runInTransaction({ ...testApp, log }, async ({ log: inner }) => {
    inner?.info({ event: 'cart.add', phase: 'did' }, 'added')
  })

  assert.notEqual(written[0]?.txn_id, written[1]?.txn_id)
})

test('a caller with nowhere to write opens a unit of work with no logger', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)

  const log = await runInTransaction(testApp, async (transacted) => transacted.log)

  assert.equal(log, undefined)
})

/** A logger that keeps the payload of every line, bindings folded in. */
function recordingLog(written: Record<string, unknown>[], bindings: LogData = {}): AppLogger {
  const record = (payload: object): void => {
    written.push({ ...bindings, ...payload })
  }

  return {
    debug: record,
    info: record,
    warn: record,
    error: record,
    child: (extra) => recordingLog(written, { ...bindings, ...extra }),
  }
}
