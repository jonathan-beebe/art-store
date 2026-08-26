import { test } from 'node:test'
import assert from 'node:assert/strict'
import type {
  CustomerId,
  FulfillmentId,
  OrderId,
} from '../../core/ids/entity-ids.ts'
import { BrokenContractError } from '../../core/defect.ts'
import { mustSucceed } from '../../core/refusal.ts'
import { fixtureId } from '../../test/fixture-ids.ts'
import { markShipped } from './mark-shipped.ts'
import type { AppDatabase } from '../../db/database.ts'
import { createCustomer, createListing, createSeller, openCommerceWorld, paidOrder } from '../../test/commerce-world.ts'

test('it records the carrier, the tracking number, and shippedAt', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  const buyer = await createCustomer(context)
  const art = await createListing(context, shop)
  const order = await paidOrder(context, buyer, [art.id])
  const [fulfillmentId] = await fulfillmentIds(world.db, order.id)

  const shipped = mustSucceed(
    await markShipped(context, {
      fulfillmentId: fulfillmentId ?? fixtureId('ful', 0),
      carrier: 'USPS',
      trackingNumber: '9400111899',
    }),
  ).fulfillment

  assert.equal(shipped.carrier, 'USPS')
  assert.equal(shipped.trackingNumber, '9400111899')
  assert.notEqual(shipped.shippedAt, null)
})

test('the only shipment of an order ships the order', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  const buyer = await createCustomer(context)
  const art = await createListing(context, shop)
  const order = await paidOrder(context, buyer, [art.id])
  const [fulfillmentId] = await fulfillmentIds(world.db, order.id)

  await markShipped(context, { fulfillmentId: fulfillmentId ?? fixtureId('ful', 0), carrier: 'USPS', trackingNumber: '9400111899' })

  assert.equal(await readOrderStatus(world.db, order.id), 'shipped')
})

test('one shipment of two partially ships the order', async (t) => {
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

  await markShipped(context, { fulfillmentId: first ?? fixtureId('ful', 0), carrier: 'USPS', trackingNumber: '9400111899' })

  assert.equal(await readOrderStatus(world.db, order.id), 'partially_shipped')
})

test('it tells the customer how to track it', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  const buyer = await createCustomer(context)
  const art = await createListing(context, shop)
  const order = await paidOrder(context, buyer, [art.id])
  const [fulfillmentId] = await fulfillmentIds(world.db, order.id)

  await markShipped(context, { fulfillmentId: fulfillmentId ?? fixtureId('ful', 0), carrier: 'USPS', trackingNumber: '9400111899' })

  const [notification] = await readNotifications(world.db, order.customerId)
  assert.equal(notification?.subject, 'Order shipped')
  assert.match(notification?.body ?? '', /9400111899/)
})

test('it refuses to ship the same fulfillment twice', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  const buyer = await createCustomer(context)
  const art = await createListing(context, shop)
  const order = await paidOrder(context, buyer, [art.id])
  const [fulfillmentId] = await fulfillmentIds(world.db, order.id)
  const resolvedId = fulfillmentId ?? fixtureId('ful', 0)

  await markShipped(context, { fulfillmentId: resolvedId, carrier: 'USPS', trackingNumber: '9400111899' })

  const result = await markShipped(context, { fulfillmentId: resolvedId, carrier: 'USPS', trackingNumber: '9400111899' })

  assert.deepEqual(result, {
    outcome: 'refused',
    reason: 'illegal_transition',
    data: { fulfillment_id: resolvedId, status_from: 'shipped', status_to: 'shipped' },
  })
  assert.equal(await readStatus(world.db, resolvedId), 'shipped')
})

test('mustSucceed throws BrokenContractError for a refusal and unwraps a shipped result', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  const buyer = await createCustomer(context)
  const art = await createListing(context, shop)
  const order = await paidOrder(context, buyer, [art.id])
  const [fulfillmentId] = await fulfillmentIds(world.db, order.id)
  const resolvedId = fulfillmentId ?? fixtureId('ful', 0)

  const shipped = mustSucceed(
    await markShipped(context, { fulfillmentId: resolvedId, carrier: 'USPS', trackingNumber: '9400111899' }),
  ).fulfillment
  assert.equal(shipped.status, 'shipped')

  const refusal = await markShipped(context, { fulfillmentId: resolvedId, carrier: 'USPS', trackingNumber: '9400111899' })

  assert.throws(
    () => mustSucceed(refusal),
    (error: unknown) => error instanceof BrokenContractError && error.reason === 'illegal_transition',
  )
})

async function fulfillmentIds(db: AppDatabase, orderId: OrderId): Promise<FulfillmentId[]> {
  const rows = await db
    .selectFrom('fulfillments')
    .select('id')
    .where('orderId', '=', orderId)
    .orderBy('sellerId')
    .execute()

  return rows.map((row) => row.id)
}

async function readOrderStatus(db: AppDatabase, orderId: OrderId): Promise<string> {
  const order = await db
    .selectFrom('orders')
    .select('status')
    .where('id', '=', orderId)
    .executeTakeFirstOrThrow()

  return order.status
}

async function readNotifications(db: AppDatabase, customerId: CustomerId) {
  return db.selectFrom('notifications').selectAll().where('customerId', '=', customerId).execute()
}

async function readStatus(db: AppDatabase, fulfillmentId: FulfillmentId): Promise<string> {
  const fulfillment = await db
    .selectFrom('fulfillments')
    .select('status')
    .where('id', '=', fulfillmentId)
    .executeTakeFirstOrThrow()

  return fulfillment.status
}
