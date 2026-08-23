import { test } from 'node:test'
import assert from 'node:assert/strict'
import { buildTestApp, TEST_INSTANT } from '../../test/build-test-app.ts'
import { claimSellerIdentity } from './claim-seller-identity.ts'
import { fixedClock } from '../../clock.ts'
import { runInTransaction } from '../transaction.ts'

test("a first link for an address creates the seller row and marks the address verified at the clock's now", async (t) => {
  const { db, clock, close } = await buildTestApp()
  t.after(close)

  const seller = await claimSellerIdentity({ db, clock }, 'artist@example.com')

  assert.equal(seller.email, 'artist@example.com')
  assert.equal(seller.emailVerifiedAt, TEST_INSTANT.toISOString())
})

test('a later link returns the seller already holding the address and creates no second row', async (t) => {
  const { db, clock, close } = await buildTestApp()
  t.after(close)

  const first = await claimSellerIdentity({ db, clock }, 'artist@example.com')
  const second = await claimSellerIdentity({ db, clock }, 'artist@example.com')

  assert.equal(second.id, first.id)
  const rows = await db.selectFrom('sellers').selectAll().where('email', '=', 'artist@example.com').execute()
  assert.equal(rows.length, 1)
})

test('a later link leaves the original verification time alone', async (t) => {
  const earlier = new Date('2026-08-01T00:00:00.000Z')
  const { db, close } = await buildTestApp({ clock: fixedClock(earlier) })
  t.after(close)

  await claimSellerIdentity({ db, clock: fixedClock(earlier) }, 'artist@example.com')
  const seller = await claimSellerIdentity({ db, clock: fixedClock(TEST_INSTANT) }, 'artist@example.com')

  assert.equal(seller.emailVerifiedAt, earlier.toISOString())
})

test('an address differing only in case reaches the same seller', async (t) => {
  const { db, clock, close } = await buildTestApp()
  t.after(close)

  const first = await claimSellerIdentity({ db, clock }, 'Artist@Example.com')
  const second = await claimSellerIdentity({ db, clock }, 'artist@example.com')

  assert.equal(second.id, first.id)
})

test('claiming the same address twice inside one transaction reaches one seller', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)

  const claimed = await runInTransaction(testApp, async (context) => [
    await claimSellerIdentity(context, 'artist@example.com'),
    await claimSellerIdentity(context, 'artist@example.com'),
  ])

  assert.deepEqual(claimed[1], claimed[0])
  assert.equal((await testApp.db.selectFrom('sellers').selectAll().execute()).length, 1)
})

test('settling an unverified address returns the row the database holds', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  await testApp.db
    .insertInto('sellers')
    .values({
      email: 'artist@example.com',
      name: 'Ada',
      shopName: null,
      emailVerifiedAt: null,
      createdAt: TEST_INSTANT.toISOString(),
    })
    .execute()

  const seller = await claimSellerIdentity(testApp, 'artist@example.com')

  const stored = await testApp.db
    .selectFrom('sellers')
    .selectAll()
    .where('email', '=', 'artist@example.com')
    .executeTakeFirstOrThrow()

  assert.deepEqual(seller, stored)
  assert.equal(seller.emailVerifiedAt, TEST_INSTANT.toISOString())
})
