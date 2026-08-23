import { test } from 'node:test'
import assert from 'node:assert/strict'
import { confirmDelivered } from '../../../actions/fulfillments/confirm-delivered.ts'
import { markShipped } from '../../../actions/fulfillments/mark-shipped.ts'
import { runWeeklyPayout } from '../../../actions/escrow/run-weekly-payout.ts'
import type { Clock } from '../../../clock.ts'
import { buildTestApp, signInAsAdmin, TEST_INSTANT } from '../../../test/build-test-app.ts'
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

test('the ledger page lists every entry with no filter applied', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)

  const admin = await signInAsAdmin(testApp)
  const context = { db: testApp.db, clock: testApp.clock }

  const sellerId = await createSeller(context, 'Blue Kiln Studio')
  const customerId = await createCustomer(context)
  const listing = await createListing(context, sellerId, { priceCents: 45_000 })
  await paidOrder(context, customerId, [listing.id])

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/ledger',
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 200)
  assert.match(response.body, /data-entry="1"/)
  assert.match(response.body, /data-cell="type"[^<]*>Held/)
  assert.match(response.body, /data-stat="held"[\s\S]*?\$405\.00/)
})

test('filtering by seller and by type narrows the rows shown', async (t) => {
  const clock = travellingClock(PLACED_AT)
  const testApp = await buildTestApp({ clock })
  t.after(testApp.close)

  const admin = await signInAsAdmin(testApp)
  const context = { db: testApp.db, clock }

  const first = await createSeller(context, 'Blue Kiln Studio')
  const second = await createSeller(context, 'Rye Press')
  const customerId = await createCustomer(context)
  const firstListing = await createListing(context, first, { priceCents: 45_000 })
  const secondListing = await createListing(context, second, { priceCents: 20_000 })
  const order = await paidOrder(context, customerId, [firstListing.id, secondListing.id])

  const fulfillments = await testApp.db
    .selectFrom('fulfillments')
    .selectAll()
    .where('orderId', '=', order.id)
    .execute()

  clock.travelTo(SHIPPED_AT)
  for (const fulfillment of fulfillments) {
    await markShipped(context, {
      fulfillmentId: fulfillment.id,
      carrier: 'Royal Mail',
      trackingNumber: 'RM123',
    })
  }

  clock.travelTo(DELIVERED_AT)
  for (const fulfillment of fulfillments) {
    await confirmDelivered(context, fulfillment.id)
  }

  clock.travelTo(TEST_INSTANT)
  await runWeeklyPayout(context, TEST_INSTANT)

  const bySeller = await testApp.app.inject({
    method: 'GET',
    url: `/admin/ledger?seller=${first}`,
    cookies: admin.cookies,
  })
  assert.equal(bySeller.statusCode, 200)
  assert.match(bySeller.body, /data-cell="seller"[^<]*>Blue Kiln Studio/)
  assert.doesNotMatch(bySeller.body, /data-cell="seller"[^<]*>Rye Press/)

  const byType = await testApp.app.inject({
    method: 'GET',
    url: '/admin/ledger?type=paid_out',
    cookies: admin.cookies,
  })
  assert.equal(byType.statusCode, 200)
  assert.match(byType.body, /data-cell="type"[^<]*>Paid out/)
  assert.doesNotMatch(byType.body, /data-cell="type"[^<]*>Held/)
})

test('the ledger filter select offers every LEDGER_ENTRY_TYPES value', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)

  const admin = await signInAsAdmin(testApp)
  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/ledger',
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 200)
  assert.match(response.body, /<option value="held"/)
  assert.match(response.body, /<option value="released"/)
  assert.match(response.body, /<option value="paid_out"/)
})

test('the "all" options submit empty filters, which the page reads as no filter', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)

  const admin = await signInAsAdmin(testApp)
  const context = { db: testApp.db, clock: testApp.clock }
  const sellerId = await createSeller(context, 'Blue Kiln Studio')
  const customerId = await createCustomer(context)
  const listing = await createListing(context, sellerId, { priceCents: 45_000 })
  await paidOrder(context, customerId, [listing.id])

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/ledger?seller=&type=',
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 200)
  assert.match(response.body, /data-cell="seller"[^<]*>Blue Kiln Studio/)
})
