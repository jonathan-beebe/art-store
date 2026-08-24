import { test } from 'node:test'
import assert from 'node:assert/strict'
import type { ListingId } from '../../core/ids/entity-ids.ts'
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

  const cancelled = await cancelOrder(context, order.id)

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

  const cancelled = await cancelOrder(context, order.id)

  assert.equal(cancelled.status, 'cancelled')
  assert.deepEqual(await readStock(world.db, art.id), { quantity: 1, status: 'for_sale' })
})

test('it refuses a paid order', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  const buyer = await createCustomer(context)
  const art = await createListing(context, shop)
  const order = await placedOrder(context, buyer, [art.id])
  await finalizeOrder(context, { orderId: order.id, cardNumber: APPROVED_CARD })

  await assert.rejects(() => cancelOrder(context, order.id), /cannot move from paid to cancelled/)
})

async function readStock(db: AppDatabase, listingId: ListingId) {
  return db
    .selectFrom('listings')
    .select(['quantity', 'status'])
    .where('id', '=', listingId)
    .executeTakeFirstOrThrow()
}
