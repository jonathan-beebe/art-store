import { test } from 'node:test'
import assert from 'node:assert/strict'
import { fulfillmentRows } from './fulfillment-rows.ts'
import { markShipped } from '../../../actions/fulfillments/mark-shipped.ts'
import {
  createCustomer,
  createListing,
  createSeller,
  openCommerceWorld,
  paidOrder,
} from '../../../test/commerce-world.ts'

test('a fulfillment carries its seller, money, and shipment timestamps', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const sellerId = await createSeller(world.context, 'Blue Kiln Studio')
  const customerId = await createCustomer(world.context)
  const listing = await createListing(world.context, sellerId, { priceCents: 45_000 })
  const order = await paidOrder(world.context, customerId, [listing.id])

  const fulfillment = await world.db
    .selectFrom('fulfillments')
    .selectAll()
    .where('orderId', '=', order.id)
    .executeTakeFirstOrThrow()

  const rows = await fulfillmentRows(world.context)

  assert.deepEqual(rows, [
    {
      id: fulfillment.id,
      orderId: order.id,
      sellerId,
      sellerName: 'Blue Kiln Studio',
      status: 'awaiting_shipment',
      subtotalCents: 45_000,
      feeCents: 4_500,
      netCents: 40_500,
      shippedAt: null,
      deliveredAt: null,
    },
  ])
})

test('a shipped fulfillment carries its shipped timestamp', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const sellerId = await createSeller(world.context)
  const customerId = await createCustomer(world.context)
  const listing = await createListing(world.context, sellerId)
  const order = await paidOrder(world.context, customerId, [listing.id])
  const fulfillment = await world.db
    .selectFrom('fulfillments')
    .selectAll()
    .where('orderId', '=', order.id)
    .executeTakeFirstOrThrow()

  await markShipped(world.context, {
    fulfillmentId: fulfillment.id,
    carrier: 'Royal Mail',
    trackingNumber: 'RM1',
  })

  const rows = await fulfillmentRows(world.context)

  assert.notEqual(rows[0]?.shippedAt, null)
  assert.equal(rows[0]?.status, 'shipped')
})

test('filters by status', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const sellerId = await createSeller(world.context)
  const customerId = await createCustomer(world.context)
  const listing = await createListing(world.context, sellerId)
  const order = await paidOrder(world.context, customerId, [listing.id])
  const fulfillment = await world.db
    .selectFrom('fulfillments')
    .selectAll()
    .where('orderId', '=', order.id)
    .executeTakeFirstOrThrow()
  await markShipped(world.context, {
    fulfillmentId: fulfillment.id,
    carrier: 'Royal Mail',
    trackingNumber: 'RM1',
  })

  const shipped = await fulfillmentRows(world.context, { status: 'shipped' })
  const awaiting = await fulfillmentRows(world.context, { status: 'awaiting_shipment' })

  assert.equal(shipped.length, 1)
  assert.equal(awaiting.length, 0)
})

test('filters by seller', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const customerId = await createCustomer(world.context)
  const first = await createSeller(world.context)
  const second = await createSeller(world.context)
  const listingA = await createListing(world.context, first)
  const listingB = await createListing(world.context, second)
  await paidOrder(world.context, customerId, [listingA.id])
  await paidOrder(world.context, customerId, [listingB.id])

  const rows = await fulfillmentRows(world.context, { sellerId: second })

  assert.equal(rows.length, 1)
  assert.equal(rows[0]?.sellerId, second)
})
