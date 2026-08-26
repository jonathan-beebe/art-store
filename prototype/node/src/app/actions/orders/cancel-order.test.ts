import { test } from 'node:test'
import assert from 'node:assert/strict'
import type { ListingId, OrderId } from '../../core/ids/entity-ids.ts'
import { BrokenContractError } from '../../core/defect.ts'
import { mustSucceed } from '../../core/refusal.ts'
import { cancelOrder } from './cancel-order.ts'
import { finalizeOrder } from './finalize-order.ts'
import type { AppDatabase } from '../../db/database.ts'
import {
  APPROVED_CARD,
  DECLINED_CARD,
  createCustomer,
  createListing,
  createSeller,
  openCommerceWorld,
  placedOrder,
} from '../../test/commerce-world.ts'

test('it cancels from awaiting_payment and restores stock', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  const buyer = await createCustomer(context)
  const art = await createListing(context, shop, { quantity: 1 })
  const order = await placedOrder(context, buyer, [art.id])

  const result = await cancelOrder(context, order.id)

  assert.equal(result.outcome, 'cancelled')
  const cancelled = mustSucceed(result).order
  assert.equal(cancelled.status, 'cancelled')
  assert.notEqual(cancelled.cancelledAt, null)
  assert.deepEqual(await readStock(world.db, art.id), { quantity: 1, status: 'for_sale' })
})

test('it cancels from payment_failed and leaves the stock alone', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  const buyer = await createCustomer(context)
  const art = await createListing(context, shop, { quantity: 1 })
  const order = await placedOrder(context, buyer, [art.id])
  await finalizeOrder(context, { orderId: order.id, cardNumber: DECLINED_CARD })

  const cancelled = mustSucceed(await cancelOrder(context, order.id)).order

  assert.equal(cancelled.status, 'cancelled')
  assert.deepEqual(await readStock(world.db, art.id), { quantity: 1, status: 'for_sale' })
})

test('it refuses a paid order, and leaves the row where it was', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  const buyer = await createCustomer(context)
  const art = await createListing(context, shop)
  const order = await placedOrder(context, buyer, [art.id])
  await finalizeOrder(context, { orderId: order.id, cardNumber: APPROVED_CARD })

  const result = await cancelOrder(context, order.id)

  assert.deepEqual(result, {
    outcome: 'refused',
    reason: 'illegal_transition',
    data: { order_id: order.id, status_from: 'paid', status_to: 'cancelled' },
  })
  assert.equal(await readOrderStatus(world.db, order.id), 'paid')
})

test('mustSucceed unwraps a cancelled result, and throws BrokenContractError carrying reason and data for a refusal', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  const buyer = await createCustomer(context)
  const art = await createListing(context, shop)
  const order = await placedOrder(context, buyer, [art.id])

  const cancelled = mustSucceed(await cancelOrder(context, order.id)).order
  assert.equal(cancelled.status, 'cancelled')

  const refusal = await cancelOrder(context, order.id)

  assert.throws(
    () => mustSucceed(refusal),
    (error: unknown) =>
      error instanceof BrokenContractError &&
      error.reason === 'illegal_transition' &&
      JSON.stringify(error.data) ===
        JSON.stringify({ order_id: order.id, status_from: 'cancelled', status_to: 'cancelled' }),
  )
})

async function readStock(db: AppDatabase, listingId: ListingId) {
  return db
    .selectFrom('listings')
    .select(['quantity', 'status'])
    .where('id', '=', listingId)
    .executeTakeFirstOrThrow()
}

async function readOrderStatus(db: AppDatabase, orderId: OrderId): Promise<string> {
  const order = await db
    .selectFrom('orders')
    .select('status')
    .where('id', '=', orderId)
    .executeTakeFirstOrThrow()

  return order.status
}
