import { test } from 'node:test'
import assert from 'node:assert/strict'
import { sweepStaleOrders } from './sweep-stale-orders.ts'
import { markAwaitingPayment } from './mark-awaiting-payment.ts'
import type { AppDatabase } from '../../db/database.ts'
import type { OrderId } from '../../core/ids/entity-ids.ts'
import {
  createCustomer,
  createListing,
  createSeller,
  openCommerceWorld,
  placedOrder,
  PLACED_AT,
} from '../../test/commerce-world.ts'

/** A day and a half after the fixture orders were placed. */
const WELL_PAST_THE_CUTOFF = new Date('2026-08-21T21:00:00.000Z')

/** An hour after the fixture orders were placed. */
const INSIDE_THE_WINDOW = new Date('2026-08-20T10:00:00.000Z')

async function readOrder(db: AppDatabase, orderId: OrderId) {
  return db
    .selectFrom('orders')
    .select(['status', 'cancelledAt'])
    .where('id', '=', orderId)
    .executeTakeFirstOrThrow()
}

test('it cancels an order left unverified past the cutoff', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context, db } = world

  const sellerId = await createSeller(context)
  const buyerId = await createCustomer(context, { isVerified: false })
  const listing = await createListing(context, sellerId)
  const order = await placedOrder(context, buyerId, [listing.id], { isVerified: false })

  world.travelTo(WELL_PAST_THE_CUTOFF)
  const cancelled = await sweepStaleOrders(context, { staleHours: 24, asOf: WELL_PAST_THE_CUTOFF })

  assert.deepEqual(
    cancelled.map((row) => row.id),
    [order.id],
  )
  assert.equal((await readOrder(db, order.id)).status, 'cancelled')
})

test('a swept order hands its stock back to the storefront', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context, db } = world

  const sellerId = await createSeller(context)
  const buyerId = await createCustomer(context, { isVerified: false })
  const listing = await createListing(context, sellerId)
  await placedOrder(context, buyerId, [listing.id], { isVerified: false })

  world.travelTo(WELL_PAST_THE_CUTOFF)
  await sweepStaleOrders(context, { staleHours: 24, asOf: WELL_PAST_THE_CUTOFF })

  const after = await db
    .selectFrom('listings')
    .select(['quantity', 'status'])
    .where('id', '=', listing.id)
    .executeTakeFirstOrThrow()

  assert.deepEqual(after, { quantity: 1, status: 'for_sale' })
})

test('it leaves an order younger than the cutoff alone', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context, db } = world

  const sellerId = await createSeller(context)
  const buyerId = await createCustomer(context, { isVerified: false })
  const listing = await createListing(context, sellerId)
  const order = await placedOrder(context, buyerId, [listing.id], { isVerified: false })

  const cancelled = await sweepStaleOrders(context, { staleHours: 24, asOf: INSIDE_THE_WINDOW })

  assert.deepEqual(cancelled, [])
  assert.equal((await readOrder(db, order.id)).status, 'pending_verification')
})

test('an order placed exactly at the cutoff is not yet stale', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context, db } = world

  const sellerId = await createSeller(context)
  const buyerId = await createCustomer(context, { isVerified: false })
  const listing = await createListing(context, sellerId)
  const order = await placedOrder(context, buyerId, [listing.id], { isVerified: false })

  const exactly = new Date(PLACED_AT.getTime() + 24 * 60 * 60 * 1000)
  const cancelled = await sweepStaleOrders(context, { staleHours: 24, asOf: exactly })

  assert.deepEqual(cancelled, [])
  assert.equal((await readOrder(db, order.id)).status, 'pending_verification')
})

test('it never touches an order that is only awaiting payment', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context, db } = world

  const sellerId = await createSeller(context)
  const buyerId = await createCustomer(context, { isVerified: false })
  const listing = await createListing(context, sellerId)
  const order = await placedOrder(context, buyerId, [listing.id], { isVerified: false })
  await markAwaitingPayment(context, order.id)

  world.travelTo(WELL_PAST_THE_CUTOFF)
  const cancelled = await sweepStaleOrders(context, { staleHours: 24, asOf: WELL_PAST_THE_CUTOFF })

  assert.deepEqual(cancelled, [])
  assert.equal((await readOrder(db, order.id)).status, 'awaiting_payment')
})

test('a second run over the same window cancels nothing', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const sellerId = await createSeller(context)
  const buyerId = await createCustomer(context, { isVerified: false })
  const listing = await createListing(context, sellerId)
  await placedOrder(context, buyerId, [listing.id], { isVerified: false })

  world.travelTo(WELL_PAST_THE_CUTOFF)
  assert.equal((await sweepStaleOrders(context, { staleHours: 24, asOf: WELL_PAST_THE_CUTOFF })).length, 1)
  assert.deepEqual(await sweepStaleOrders(context, { staleHours: 24, asOf: WELL_PAST_THE_CUTOFF }), [])
})

test('a shorter window reaches orders a longer one would leave', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const sellerId = await createSeller(context)
  const buyerId = await createCustomer(context, { isVerified: false })
  const listing = await createListing(context, sellerId)
  await placedOrder(context, buyerId, [listing.id], { isVerified: false })

  world.travelTo(INSIDE_THE_WINDOW)
  assert.deepEqual(await sweepStaleOrders(context, { staleHours: 24, asOf: INSIDE_THE_WINDOW }), [])
  assert.equal(
    (await sweepStaleOrders(context, { staleHours: 1, asOf: new Date('2026-08-20T10:00:01.000Z') })).length,
    1,
  )
})
