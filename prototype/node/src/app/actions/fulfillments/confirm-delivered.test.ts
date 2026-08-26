import { test } from 'node:test'
import assert from 'node:assert/strict'
import type {
  CustomerId,
  FulfillmentId,
  OrderId,
  SellerId,
} from '../../core/ids/entity-ids.ts'
import { BrokenContractError } from '../../core/defect.ts'
import { mustSucceed } from '../../core/refusal.ts'
import { fixtureId } from '../../test/fixture-ids.ts'
import { confirmDelivered } from './confirm-delivered.ts'
import { markShipped } from './mark-shipped.ts'
import { sellerBalance } from '../escrow/ledger-balances.ts'
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

  const delivered = mustSucceed(await confirmDelivered(context, fulfillmentId)).fulfillment

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

  const delivered = mustSucceed(await confirmDelivered(context, fulfillmentId)).fulfillment
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
  const resolvedId = fulfillmentId ?? fixtureId('ful', 0)

  const result = await confirmDelivered(context, resolvedId)

  assert.deepEqual(result, {
    outcome: 'refused',
    reason: 'illegal_transition',
    data: { fulfillment_id: resolvedId, status_from: 'awaiting_shipment', status_to: 'delivered' },
  })
  assert.equal(await readReleasedEntry(world.db), undefined)
})

test('mustSucceed unwraps a delivered result, and throws BrokenContractError carrying reason and data for a refusal', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  const buyer = await createCustomer(context)
  const fulfillmentId = await shippedFulfillmentId(context, world.db, buyer, shop)

  const delivered = mustSucceed(await confirmDelivered(context, fulfillmentId)).fulfillment
  assert.equal(delivered.status, 'delivered')

  const refusal = await confirmDelivered(context, fulfillmentId)

  assert.throws(
    () => mustSucceed(refusal),
    (error: unknown) =>
      error instanceof BrokenContractError &&
      error.reason === 'illegal_transition' &&
      JSON.stringify(error.data) ===
        JSON.stringify({ fulfillment_id: fulfillmentId, status_from: 'delivered', status_to: 'delivered' }),
  )
})

/** A fulfillment shipped and ready for delivery confirmation. */
async function shippedFulfillmentId(
  context: ActionContext,
  db: AppDatabase,
  buyer: CustomerId,
  sellerId: SellerId,
): Promise<FulfillmentId> {
  const art = await createListing(context, sellerId)
  const order = await paidOrder(context, buyer, [art.id])
  const [fulfillmentId] = await fulfillmentIds(db, order.id)
  await markShipped(context, { fulfillmentId: fulfillmentId ?? fixtureId('ful', 0), carrier: 'USPS', trackingNumber: '9400111899' })
  return fulfillmentId ?? fixtureId('ful', 0)
}

async function fulfillmentIds(db: AppDatabase, orderId: OrderId): Promise<FulfillmentId[]> {
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

async function readOrderStatus(db: AppDatabase, fulfillmentId: FulfillmentId): Promise<string> {
  const fulfillment = await db
    .selectFrom('fulfillments')
    .innerJoin('orders', 'orders.id', 'fulfillments.orderId')
    .select('orders.status as status')
    .where('fulfillments.id', '=', fulfillmentId)
    .executeTakeFirstOrThrow()

  return fulfillment.status
}
