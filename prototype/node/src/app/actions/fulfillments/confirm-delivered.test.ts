import { test } from 'node:test'
import assert from 'node:assert/strict'
import { confirmDelivered } from './confirm-delivered.ts'
import { markShipped } from './mark-shipped.ts'
import { sellerBalance } from '../escrow/seller-balance.ts'
import type { ActionContext } from '../action-context.ts'
import type { AppDatabase } from '../../db/database.ts'
import { createCustomer, createListing, createSeller, openCommerceWorld, paidOrder } from '../../test/commerce-world.ts'

test('it records when the order arrived', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  const buyer = await createCustomer(context)
  const fulfillmentId = await shippedFulfillmentId(context, world.db, buyer, shop)

  const delivered = await confirmDelivered(context, fulfillmentId)

  assert.equal(delivered.status, 'delivered')
  assert.notEqual(delivered.deliveredAt, null)
})

test('delivery releases the escrow the sale held', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  const buyer = await createCustomer(context)
  const fulfillmentId = await shippedFulfillmentId(context, world.db, buyer, shop)

  const delivered = await confirmDelivered(context, fulfillmentId)
  const entry = await readReleasedEntry(world.db)

  assert.equal(entry?.amountCents, 40_500)
  assert.equal(entry?.sellerId, shop)
  assert.equal(entry?.occurredAt, delivered.deliveredAt)
})

test('released money becomes available to the seller', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  const buyer = await createCustomer(context)
  const fulfillmentId = await shippedFulfillmentId(context, world.db, buyer, shop)

  await confirmDelivered(context, fulfillmentId)
  const balance = await sellerBalance(context, shop)

  assert.equal(balance.heldCents, 0)
  assert.equal(balance.availableCents, 40_500)
})

test('the last delivery of an order delivers the order', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  const buyer = await createCustomer(context)
  const fulfillmentId = await shippedFulfillmentId(context, world.db, buyer, shop)

  await confirmDelivered(context, fulfillmentId)

  assert.equal(await readOrderStatus(world.db, fulfillmentId), 'delivered')
})

test('it refuses a fulfillment that has not shipped', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  const buyer = await createCustomer(context)
  const art = await createListing(context, shop)
  const order = await paidOrder(context, buyer, [art.id])
  const [fulfillmentId] = await fulfillmentIds(world.db, order.id)

  await assert.rejects(
    () => confirmDelivered(context, fulfillmentId ?? 0),
    /cannot move from awaiting_shipment to delivered/,
  )
})

/** A fulfillment shipped and ready for delivery confirmation. */
async function shippedFulfillmentId(
  context: ActionContext,
  db: AppDatabase,
  buyer: number,
  sellerId: number,
): Promise<number> {
  const art = await createListing(context, sellerId)
  const order = await paidOrder(context, buyer, [art.id])
  const [fulfillmentId] = await fulfillmentIds(db, order.id)
  await markShipped(context, { fulfillmentId: fulfillmentId ?? 0, carrier: 'USPS', trackingNumber: '9400111899' })
  return fulfillmentId ?? 0
}

async function fulfillmentIds(db: AppDatabase, orderId: number): Promise<number[]> {
  const rows = await db
    .selectFrom('fulfillments')
    .select('id')
    .where('orderId', '=', orderId)
    .orderBy('sellerId')
    .execute()

  return rows.map((row) => row.id)
}

async function readReleasedEntry(db: AppDatabase) {
  return db.selectFrom('ledgerEntries').selectAll().where('entryType', '=', 'released').executeTakeFirst()
}

async function readOrderStatus(db: AppDatabase, fulfillmentId: number): Promise<string> {
  const fulfillment = await db
    .selectFrom('fulfillments')
    .innerJoin('orders', 'orders.id', 'fulfillments.orderId')
    .select('orders.status as status')
    .where('fulfillments.id', '=', fulfillmentId)
    .executeTakeFirstOrThrow()

  return fulfillment.status
}
