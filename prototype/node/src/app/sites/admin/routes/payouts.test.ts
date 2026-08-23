import { test } from 'node:test'
import assert from 'node:assert/strict'
import type { LightMyRequestResponse } from 'fastify'
import { confirmDelivered } from '../../../actions/fulfillments/confirm-delivered.ts'
import { markShipped } from '../../../actions/fulfillments/mark-shipped.ts'
import type { Clock } from '../../../clock.ts'
import {
  buildTestApp,
  signInAsAdmin,
  TEST_INSTANT,
  type TestApp,
} from '../../../test/build-test-app.ts'
import { createCustomer, createListing, createSeller, paidOrder } from '../../../test/commerce-world.ts'

const PLACED_AT = new Date('2026-08-20T09:00:00.000Z')
const SHIPPED_AT = new Date('2026-08-20T11:00:00.000Z')
const DELIVERED_AT = new Date('2026-08-21T11:00:00.000Z')

type TravellingClock = Clock & { travelTo(instant: Date): void }

function travellingClock(instant: Date): TravellingClock {
  let current = instant

  return {
    now: () => new Date(current),
    travelTo: (next: Date) => {
      current = next
    },
  }
}

async function deliverASale(
  context: { db: Awaited<ReturnType<typeof buildTestApp>>['db']; clock: TravellingClock },
  sellerId: number,
  priceCents: number,
): Promise<void> {
  const customerId = await createCustomer(context)
  const listing = await createListing(context, sellerId, { priceCents })
  const order = await paidOrder(context, customerId, [listing.id])

  const fulfillment = await context.db
    .selectFrom('fulfillments')
    .selectAll()
    .where('orderId', '=', order.id)
    .executeTakeFirstOrThrow()

  context.clock.travelTo(SHIPPED_AT)
  await markShipped(context, {
    fulfillmentId: fulfillment.id,
    carrier: 'Royal Mail',
    trackingNumber: 'RM123',
  })

  context.clock.travelTo(DELIVERED_AT)
  await confirmDelivered(context, fulfillment.id)

  context.clock.travelTo(PLACED_AT)
}

test('the payouts page defaults its date field to today and shows the run form', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)

  const admin = await signInAsAdmin(testApp)
  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/payouts',
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 200)
  assert.match(response.body, /name="as_of"[^>]*value="2026-08-24"/)
  assert.match(response.body, /Run weekly payout/)
})

test('filtering by seller shows only that seller’s payouts', async (t) => {
  const clock = travellingClock(PLACED_AT)
  const testApp = await buildTestApp({ clock })
  t.after(testApp.close)

  const admin = await signInAsAdmin(testApp)
  const context = { db: testApp.db, clock }

  const first = await createSeller(context, 'Blue Kiln Studio')
  const second = await createSeller(context, 'Rye Press')
  await deliverASale(context, first, 45_000)
  await deliverASale(context, second, 10_000)

  const runResponse = await testApp.app.inject({
    method: 'POST',
    url: '/admin/payouts',
    cookies: admin.cookies,
    payload: { as_of: '2026-08-24' },
  })
  assert.equal(runResponse.statusCode, 302)

  const payouts = await testApp.db.selectFrom('payouts').selectAll().execute()
  const firstPayout = payouts.find((payout) => payout.sellerId === first)
  const secondPayout = payouts.find((payout) => payout.sellerId === second)

  const filtered = await testApp.app.inject({
    method: 'GET',
    url: `/admin/payouts?seller=${first}`,
    cookies: admin.cookies,
  })

  assert.equal(filtered.statusCode, 200)
  assert.match(filtered.body, new RegExp(`data-payout="${firstPayout?.id}"`))
  assert.doesNotMatch(filtered.body, new RegExp(`data-payout="${secondPayout?.id}"`))
})

test('running a payout creates the payouts and paid_out ledger entries, flashes what it created, and shows up on the next page', async (t) => {
  const clock = travellingClock(PLACED_AT)
  const testApp = await buildTestApp({ clock })
  t.after(testApp.close)

  const admin = await signInAsAdmin(testApp)
  const context = { db: testApp.db, clock }

  const first = await createSeller(context, 'Blue Kiln Studio')
  const second = await createSeller(context, 'Rye Press')
  await deliverASale(context, first, 45_000)
  await deliverASale(context, second, 10_000)

  clock.travelTo(TEST_INSTANT)
  const response = await testApp.app.inject({
    method: 'POST',
    url: '/admin/payouts',
    cookies: admin.cookies,
    payload: { as_of: '2026-08-24' },
  })

  assert.equal(response.statusCode, 302)
  assert.equal(response.headers.location, '/admin/payouts')

  const payouts = await testApp.db.selectFrom('payouts').selectAll().execute()
  assert.equal(payouts.length, 2)

  const paidOutEntries = await testApp.db
    .selectFrom('ledgerEntries')
    .selectAll()
    .where('entryType', '=', 'paid_out')
    .execute()
  assert.equal(paidOutEntries.length, 2)

  const flashed = flashNotice(testApp, response)
  assert.equal(flashed, 'Paid 2 seller(s) $495.00 for 2026-08-17 to 2026-08-23.')

  const page = await testApp.app.inject({
    method: 'GET',
    url: '/admin/payouts',
    cookies: admin.cookies,
  })
  assert.match(page.body, new RegExp(`data-payout="${payouts[0]?.id}"`))
  assert.match(page.body, new RegExp(`data-payout="${payouts[1]?.id}"`))
})

test('a second run for the same as-of date creates nothing and says so', async (t) => {
  const clock = travellingClock(PLACED_AT)
  const testApp = await buildTestApp({ clock })
  t.after(testApp.close)

  const admin = await signInAsAdmin(testApp)
  const context = { db: testApp.db, clock }

  const sellerId = await createSeller(context, 'Blue Kiln Studio')
  await deliverASale(context, sellerId, 45_000)

  clock.travelTo(TEST_INSTANT)
  await testApp.app.inject({
    method: 'POST',
    url: '/admin/payouts',
    cookies: admin.cookies,
    payload: { as_of: '2026-08-24' },
  })

  const second = await testApp.app.inject({
    method: 'POST',
    url: '/admin/payouts',
    cookies: admin.cookies,
    payload: { as_of: '2026-08-24' },
  })

  assert.equal(second.statusCode, 302)
  assert.equal(flashNotice(testApp, second), 'No seller had a released balance to pay for 2026-08-17 to 2026-08-23.')

  const payouts = await testApp.db.selectFrom('payouts').select('id').execute()
  assert.equal(payouts.length, 1)
})

test('an absent as_of falls back to the clock’s today', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)

  const admin = await signInAsAdmin(testApp)
  const response = await testApp.app.inject({
    method: 'POST',
    url: '/admin/payouts',
    cookies: admin.cookies,
    payload: {},
  })

  assert.equal(response.statusCode, 302)
  assert.equal(flashNotice(testApp, response), 'No seller had a released balance to pay for 2026-08-17 to 2026-08-23.')
})

function flashNotice(testApp: TestApp, response: LightMyRequestResponse): string {
  const cookie = response.cookies.find((candidate) => candidate.name === 'flash')
  if (cookie === undefined) throw new Error('no flash cookie set')

  const unsigned = testApp.app.unsignCookie(String(cookie.value))
  const flash: unknown = JSON.parse(unsigned.value ?? '{}')

  return (flash as { notice?: string }).notice ?? ''
}
