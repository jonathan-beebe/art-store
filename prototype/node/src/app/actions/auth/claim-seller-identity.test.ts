import { test } from 'node:test'
import assert from 'node:assert/strict'
import { buildTestApp, TEST_INSTANT } from '../../test/build-test-app.ts'
import { claimSellerIdentity } from './claim-seller-identity.ts'
import { fixedClock } from '../../clock.ts'

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
