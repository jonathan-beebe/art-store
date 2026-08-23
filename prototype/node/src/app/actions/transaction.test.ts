import { test } from 'node:test'
import assert from 'node:assert/strict'
import { buildTestApp } from '../test/build-test-app.ts'
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
