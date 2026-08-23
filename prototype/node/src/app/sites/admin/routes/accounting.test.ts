import { test } from 'node:test'
import assert from 'node:assert/strict'
import { confirmDelivered } from '../../../actions/fulfillments/confirm-delivered.ts'
import { markShipped } from '../../../actions/fulfillments/mark-shipped.ts'
import { runWeeklyPayout } from '../../../actions/escrow/run-weekly-payout.ts'
import type { Clock } from '../../../clock.ts'
import { formatCents } from '../../../core/money.ts'
import {
  buildTestApp,
  signInAsAdmin,
  TEST_INSTANT,
} from '../../../test/build-test-app.ts'
import { createCustomer, createListing, createSeller, paidOrder } from '../../../test/commerce-world.ts'

// Inside the week ending 2026-08-23, the last completed week as of TEST_INSTANT.
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

test('the accounting page lists every seller and reconciles zero-activity sellers', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)

  const admin = await signInAsAdmin(testApp)
  const context = { db: testApp.db, clock: testApp.clock }
  const sellerId = await createSeller(context, 'Blue Kiln Studio')

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/accounting',
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 200)
  assert.match(response.body, new RegExp(`data-seller="${sellerId}"[\\s\\S]*?data-totals="platform"`))
  assert.match(response.body, /data-cell="reconciles"[^<]*>Yes/)
})

test('after a delivered sale and a payout run, the seller row and the platform row both reconcile', async (t) => {
  const clock = travellingClock(PLACED_AT)
  const testApp = await buildTestApp({ clock })
  t.after(testApp.close)

  const admin = await signInAsAdmin(testApp)
  const context = { db: testApp.db, clock }

  const sellerId = await createSeller(context, 'Blue Kiln Studio')
  const customerId = await createCustomer(context)
  const listing = await createListing(context, sellerId, { priceCents: 45_000 })
  const order = await paidOrder(context, customerId, [listing.id])

  const fulfillment = await testApp.db
    .selectFrom('fulfillments')
    .selectAll()
    .where('orderId', '=', order.id)
    .executeTakeFirstOrThrow()

  clock.travelTo(SHIPPED_AT)
  await markShipped(context, {
    fulfillmentId: fulfillment.id,
    carrier: 'Royal Mail',
    trackingNumber: 'RM123',
  })

  clock.travelTo(DELIVERED_AT)
  await confirmDelivered(context, fulfillment.id)

  clock.travelTo(TEST_INSTANT)
  const payouts = await runWeeklyPayout(context, TEST_INSTANT)
  const paidCents = payouts[0]?.amountCents ?? 0
  assert.equal(paidCents, 40_500)

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/accounting',
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 200)

  const sellerRow = rowSlice(response.body, `data-seller="${sellerId}"`)
  const platformRow = rowSlice(response.body, 'data-totals="platform"')

  assert.equal(cellValue(sellerRow, 'paid-out'), cellValue(sellerRow, 'payout-total'))
  assert.equal(cellValue(sellerRow, 'reconciles'), 'Yes')
  assert.equal(cellValue(sellerRow, 'paid-out'), formatCents(paidCents))
  assert.equal(cellValue(platformRow, 'paid-out'), formatCents(paidCents))
})

function rowSlice(html: string, rowMarker: string): string {
  const pattern = new RegExp(`<tr[^>]*${escapeRegExp(rowMarker)}[^>]*>([\\s\\S]*?)</tr>`)
  const row = pattern.exec(html)?.[1]
  if (row === undefined) throw new Error(`no row found for ${rowMarker}`)

  return row
}

function cellValue(rowHtml: string, cell: string): string {
  const pattern = new RegExp(`data-cell="${cell}"[^>]*>\\s*([^<]+?)\\s*<`)
  const match = pattern.exec(rowHtml)
  if (match === null) throw new Error(`no ${cell} cell in row`)

  return match[1] ?? ''
}

function escapeRegExp(value: string): string {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}
