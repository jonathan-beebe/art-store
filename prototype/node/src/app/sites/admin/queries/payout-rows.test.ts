import { test } from 'node:test'
import assert from 'node:assert/strict'
import { payoutRows } from './payout-rows.ts'
import { confirmDelivered } from '../../../actions/fulfillments/confirm-delivered.ts'
import { markShipped } from '../../../actions/fulfillments/mark-shipped.ts'
import { runWeeklyPayout } from '../../../actions/escrow/run-weekly-payout.ts'
import {
  createCustomer,
  createListing,
  createSeller,
  openCommerceWorld,
  paidOrder,
} from '../../../test/commerce-world.ts'

async function deliverAndPay(
  world: Awaited<ReturnType<typeof openCommerceWorld>>,
  sellerId: number,
  priceCents: number,
): Promise<void> {
  const customerId = await createCustomer(world.context)
  const listing = await createListing(world.context, sellerId, { priceCents })
  const order = await paidOrder(world.context, customerId, [listing.id])

  const fulfillment = await world.db
    .selectFrom('fulfillments')
    .selectAll()
    .where('orderId', '=', order.id)
    .executeTakeFirstOrThrow()

  await markShipped(world.context, {
    fulfillmentId: fulfillment.id,
    carrier: 'Royal Mail',
    trackingNumber: 'RM123',
  })
  await confirmDelivered(world.context, fulfillment.id)
  await runWeeklyPayout(world.context, new Date('2026-08-24T12:00:00.000Z'))
}

test('no payouts yet is an empty list', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  assert.deepEqual(await payoutRows(world.context), [])
})

test('a payout row names the seller and carries the period, amount, and paid-at instant', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const sellerId = await createSeller(world.context, 'Blue Kiln Studio')
  await deliverAndPay(world, sellerId, 45_000)

  const [row] = await payoutRows(world.context)
  assert.equal(row?.sellerId, sellerId)
  assert.equal(row?.sellerName, 'Blue Kiln Studio')
  assert.equal(row?.periodStart, '2026-08-17')
  assert.equal(row?.periodEnd, '2026-08-23')
  assert.equal(row?.amountCents, 40_500)
  assert.equal(row?.paidAt, '2026-08-24T12:00:00.000Z')
})

test('filtering by seller returns only that seller’s payouts', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const first = await createSeller(world.context, 'Blue Kiln Studio')
  const second = await createSeller(world.context, 'Rye Press')
  await deliverAndPay(world, first, 45_000)
  await deliverAndPay(world, second, 10_000)

  const rows = await payoutRows(world.context, { sellerId: first })
  assert.equal(rows.length, 1)
  assert.equal(rows[0]?.sellerId, first)
})
