import { test } from 'node:test'
import assert from 'node:assert/strict'
import { orderRows } from './order-rows.ts'
import { markShipped } from '../../../actions/fulfillments/mark-shipped.ts'
import { toTimestamp } from '../../../db/timestamp.ts'
import {
  createCustomer,
  createListing,
  createSeller,
  openCommerceWorld,
  paidOrder,
  placedOrder,
  SHIPPING_ADDRESS,
} from '../../../test/commerce-world.ts'

test('an order rolls up its item count, money, and fulfillment statuses', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const sellerId = await createSeller(world.context)
  const customerId = await createCustomer(world.context)
  const listing = await createListing(world.context, sellerId, { priceCents: 45_000 })
  const order = await paidOrder(world.context, customerId, [listing.id])

  const rows = await orderRows(world.context)

  assert.deepEqual(rows, [
    {
      id: order.id,
      customerEmail: 'ada@example.test',
      status: 'paid',
      itemCount: 1,
      subtotalCents: 45_000,
      totalCents: 45_000,
      placedAt: order.placedAt,
      fulfillmentStatuses: ['awaiting_shipment'],
    },
  ])
})

test('an order with no email on file carries a null customer email', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const customerId = await createCustomer(world.context, { isVerified: false })
  const now = toTimestamp(world.context.clock.now())
  await world.db
    .insertInto('orders')
    .values({
      customerId,
      email: null,
      status: 'pending_verification',
      shippingName: SHIPPING_ADDRESS.name,
      shippingLine1: SHIPPING_ADDRESS.line1,
      shippingLine2: SHIPPING_ADDRESS.line2,
      shippingCity: SHIPPING_ADDRESS.city,
      shippingRegion: SHIPPING_ADDRESS.region,
      shippingPostalCode: SHIPPING_ADDRESS.postalCode,
      shippingCountry: SHIPPING_ADDRESS.country,
      subtotalCents: 0,
      totalCents: 0,
      placedAt: now,
      finalizedAt: null,
      cancelledAt: null,
    })
    .execute()

  const rows = await orderRows(world.context)

  assert.equal(rows[0]?.customerEmail, null)
})

test('a multi-seller order rolls up more than one fulfillment status', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const customerId = await createCustomer(world.context)
  const first = await createListing(world.context, await createSeller(world.context), { priceCents: 10_000 })
  const second = await createListing(world.context, await createSeller(world.context), { priceCents: 20_000 })
  const order = await paidOrder(world.context, customerId, [first.id, second.id])

  const fulfillments = await world.db
    .selectFrom('fulfillments')
    .selectAll()
    .where('orderId', '=', order.id)
    .execute()
  const shippedOne = fulfillments[0]
  if (shippedOne === undefined) throw new Error('expected a fulfillment')
  await markShipped(world.context, {
    fulfillmentId: shippedOne.id,
    carrier: 'Royal Mail',
    trackingNumber: 'RM1',
  })

  const rows = await orderRows(world.context)

  assert.deepEqual(
    rows[0]?.fulfillmentStatuses.slice().sort(),
    ['awaiting_shipment', 'shipped'].sort(),
  )
})

test('an order with several units of one listing counts every unit', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const sellerId = await createSeller(world.context)
  const customerId = await createCustomer(world.context)
  const listing = await createListing(world.context, sellerId, { quantity: 5 })
  const order = await paidOrder(world.context, customerId, [listing.id, listing.id])

  const rows = await orderRows(world.context)

  assert.equal(rows.find((row) => row.id === order.id)?.itemCount, 2)
})

test('filters by status', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const sellerId = await createSeller(world.context)
  const customerId = await createCustomer(world.context)
  const listing = await createListing(world.context, sellerId)
  const paid = await paidOrder(world.context, customerId, [listing.id])
  const other = await createListing(world.context, sellerId)
  await placedOrder(world.context, customerId, [other.id])

  const rows = await orderRows(world.context, { status: 'paid' })

  assert.deepEqual(rows.map((row) => row.id), [paid.id])
})

test('filters by customer', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const sellerId = await createSeller(world.context)
  const first = await createCustomer(world.context)
  const second = await createCustomer(world.context)
  const listing = await createListing(world.context, sellerId)
  await placedOrder(world.context, first, [listing.id])
  const other = await createListing(world.context, sellerId)
  const wanted = await placedOrder(world.context, second, [other.id])

  const rows = await orderRows(world.context, { customerId: second })

  assert.deepEqual(rows.map((row) => row.id), [wanted.id])
})

test('newest orders come first', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const sellerId = await createSeller(world.context)
  const customerId = await createCustomer(world.context)
  const listingA = await createListing(world.context, sellerId)
  const first = await placedOrder(world.context, customerId, [listingA.id])
  const listingB = await createListing(world.context, sellerId)
  const second = await placedOrder(world.context, customerId, [listingB.id])

  const rows = await orderRows(world.context)

  assert.deepEqual(rows.map((row) => row.id), [second.id, first.id])
})
