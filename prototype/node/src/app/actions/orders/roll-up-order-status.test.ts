import { test } from 'node:test'
import assert from 'node:assert/strict'
import { rollUpOrderStatus } from './roll-up-order-status.ts'
import type { FulfillmentStatus } from '../../core/orders/fulfillment-status.ts'
import type { AppDatabase } from '../../db/database.ts'
import { createCustomer, createListing, createSeller, openCommerceWorld, paidOrder } from '../../test/commerce-world.ts'

test('an order whose fulfillments all await shipment stays paid', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  const buyer = await createCustomer(context)
  const art = await createListing(context, shop)
  const order = await paidOrder(context, buyer, [art.id])

  const rolled = await rollUpOrderStatus(context, order.id)

  assert.equal(rolled.status, 'paid')
})

test('one shipped fulfillment of two partially ships the order', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const painter = await createSeller(context, 'Blue Kiln Studio')
  const printer = await createSeller(context, 'Rye Press')
  const buyer = await createCustomer(context)
  const painting = await createListing(context, painter)
  const print = await createListing(context, printer)
  const order = await paidOrder(context, buyer, [painting.id, print.id])
  const [first] = await fulfillmentIds(world.db, order.id)
  await setFulfillmentStatus(world.db, first ?? 0, 'shipped')

  const rolled = await rollUpOrderStatus(context, order.id)

  assert.equal(rolled.status, 'partially_shipped')
})

test('every fulfillment delivered delivers the order', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  const buyer = await createCustomer(context)
  const art = await createListing(context, shop)
  const order = await paidOrder(context, buyer, [art.id])
  const [only] = await fulfillmentIds(world.db, order.id)
  await setFulfillmentStatus(world.db, only ?? 0, 'delivered')

  const rolled = await rollUpOrderStatus(context, order.id)

  assert.equal(rolled.status, 'delivered')
})

async function fulfillmentIds(db: AppDatabase, orderId: number): Promise<number[]> {
  const rows = await db
    .selectFrom('fulfillments')
    .select('id')
    .where('orderId', '=', orderId)
    .orderBy('sellerId')
    .execute()

  return rows.map((row) => row.id)
}

async function setFulfillmentStatus(db: AppDatabase, fulfillmentId: number, status: FulfillmentStatus): Promise<void> {
  await db.updateTable('fulfillments').set({ status }).where('id', '=', fulfillmentId).execute()
}
