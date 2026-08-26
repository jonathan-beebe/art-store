import { test } from 'node:test'
import assert from 'node:assert/strict'
import { issueRefund } from './issue-refund.ts'
import { declineFulfillment } from '../fulfillments/decline-fulfillment.ts'
import { confirmDelivered } from '../fulfillments/confirm-delivered.ts'
import { markShipped } from '../fulfillments/mark-shipped.ts'
import { sellerBalance } from '../escrow/ledger-balances.ts'
import { runWeeklyPayout } from '../escrow/run-weekly-payout.ts'
import type { ActionContext } from '../action-context.ts'
import type { AdminId, FulfillmentId, ListingId, OrderId, SellerId } from '../../core/ids/entity-ids.ts'
import type { AppDatabase } from '../../db/database.ts'
import {
  createAdmin,
  createCustomer,
  createListing,
  createSeller,
  openCommerceWorld,
  paidOrder,
  placedOrder,
} from '../../test/commerce-world.ts'

const REASON = 'The piece cracked in the kiln.'

async function onlyFulfillmentId(db: AppDatabase, orderId: OrderId): Promise<FulfillmentId> {
  const row = await db
    .selectFrom('fulfillments')
    .select('id')
    .where('orderId', '=', orderId)
    .executeTakeFirstOrThrow()

  return row.id
}

/** The one fulfillment on a two-seller order that belongs to `sellerId`. */
async function fulfillmentIdOf(
  db: AppDatabase,
  orderId: OrderId,
  sellerId: SellerId,
): Promise<FulfillmentId> {
  const row = await db
    .selectFrom('fulfillments')
    .select('id')
    .where('orderId', '=', orderId)
    .where('sellerId', '=', sellerId)
    .executeTakeFirstOrThrow()

  return row.id
}

function byAdmin(adminId: AdminId) {
  return { type: 'admin', id: adminId } as const
}

function bySeller(sellerId: SellerId) {
  return { type: 'seller', id: sellerId } as const
}

async function readListing(db: AppDatabase, listingId: ListingId) {
  return db
    .selectFrom('listings')
    .select(['quantity', 'status'])
    .where('id', '=', listingId)
    .executeTakeFirstOrThrow()
}

async function readOrderStatus(db: AppDatabase, orderId: OrderId) {
  return db
    .selectFrom('orders')
    .select(['status', 'refundedCents'])
    .where('id', '=', orderId)
    .executeTakeFirstOrThrow()
}

async function readFulfillmentStatus(db: AppDatabase, fulfillmentId: FulfillmentId): Promise<string> {
  const row = await db
    .selectFrom('fulfillments')
    .select('status')
    .where('id', '=', fulfillmentId)
    .executeTakeFirstOrThrow()

  return row.status
}

/** How many `refunds` rows a fulfillment has, so a refused reversal can be
 * checked for writing none. */
async function refundCount(db: AppDatabase, fulfillmentId: FulfillmentId): Promise<number> {
  const rows = await db.selectFrom('refunds').select('id').where('fulfillmentId', '=', fulfillmentId).execute()

  return rows.length
}

/** A shipped fulfillment on a paid order, the state an admin refund starts from. */
async function shippedSale(context: ActionContext, db: AppDatabase) {
  const sellerId = await createSeller(context)
  const buyerId = await createCustomer(context)
  const listing = await createListing(context, sellerId)
  const order = await paidOrder(context, buyerId, [listing.id])
  const fulfillmentId = await onlyFulfillmentId(db, order.id)
  await markShipped(context, { fulfillmentId, carrier: 'USPS', trackingNumber: '9400111899' })

  return { sellerId, buyerId, listing, order, fulfillmentId }
}

test('a seller decline records the refund against the order', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context, db } = world

  const sellerId = await createSeller(context)
  const buyerId = await createCustomer(context)
  const listing = await createListing(context, sellerId)
  const order = await paidOrder(context, buyerId, [listing.id])
  const fulfillmentId = await onlyFulfillmentId(db, order.id)

  const result = await issueRefund(context, {
    fulfillmentId,
    reason: REASON,
    issuedBy: bySeller(sellerId),
  })

  assert.equal(result.outcome, 'issued')
  assert(result.outcome === 'issued')
  const { refund, fulfillment } = result

  assert.equal(fulfillment.status, 'declined')
  assert.equal(refund.amountCents, 45_000)
  assert.equal(refund.issuedByType, 'seller')
  assert.equal(refund.issuedById, sellerId)
  assert.equal(refund.reason, REASON)
  assert.equal(refund.orderId, order.id)
})

test('a decline hands exactly the declined quantities back to the storefront', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context, db } = world

  const sellerId = await createSeller(context)
  const buyerId = await createCustomer(context)
  const listing = await createListing(context, sellerId, { quantity: 1 })
  const order = await paidOrder(context, buyerId, [listing.id])
  assert.deepEqual(await readListing(db, listing.id), { quantity: 0, status: 'sold' })

  await declineFulfillment(context, {
    fulfillmentId: await onlyFulfillmentId(db, order.id),
    sellerId,
    reason: REASON,
  })

  assert.deepEqual(await readListing(db, listing.id), { quantity: 1, status: 'for_sale' })
})

test('a decline leaves the other seller\'s stock where it is', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context, db } = world

  const declining = await createSeller(context, 'Blue Kiln Studio')
  const shipping = await createSeller(context, 'Red Barn Press')
  const buyerId = await createCustomer(context)
  const declined = await createListing(context, declining)
  const kept = await createListing(context, shipping)
  const order = await paidOrder(context, buyerId, [declined.id, kept.id])

  await declineFulfillment(context, {
    fulfillmentId: await fulfillmentIdOf(db, order.id, declining),
    sellerId: declining,
    reason: REASON,
  })

  assert.deepEqual(await readListing(db, declined.id), { quantity: 1, status: 'for_sale' })
  assert.deepEqual(await readListing(db, kept.id), { quantity: 0, status: 'sold' })
})

test('an admin refund restores nothing', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context, db } = world

  const adminId = await createAdmin(context)
  const { listing, fulfillmentId } = await shippedSale(context, db)

  const result = await issueRefund(context, {
    fulfillmentId,
    reason: 'The customer never received it.',
    issuedBy: byAdmin(adminId),
  })

  assert.equal(result.outcome, 'issued')
  assert(result.outcome === 'issued')
  assert.equal(result.fulfillment.status, 'refunded')
  assert.deepEqual(await readListing(db, listing.id), { quantity: 0, status: 'sold' })
})

test('an admin refunds a fulfillment that has not shipped', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context, db } = world

  const adminId = await createAdmin(context)
  const sellerId = await createSeller(context)
  const buyerId = await createCustomer(context)
  const listing = await createListing(context, sellerId)
  const order = await paidOrder(context, buyerId, [listing.id])

  const result = await issueRefund(context, {
    fulfillmentId: await onlyFulfillmentId(db, order.id),
    reason: 'The seller has gone silent.',
    issuedBy: byAdmin(adminId),
  })

  assert.equal(result.outcome, 'issued')
  assert(result.outcome === 'issued')
  assert.equal(result.fulfillment.status, 'refunded')
  assert.deepEqual(await readListing(db, listing.id), { quantity: 0, status: 'sold' })
})

test('an admin refunds a delivered fulfillment as a dispute outcome', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context, db } = world

  const adminId = await createAdmin(context)
  const { fulfillmentId } = await shippedSale(context, db)
  await confirmDelivered(context, fulfillmentId)

  const result = await issueRefund(context, {
    fulfillmentId,
    reason: 'It arrived broken.',
    issuedBy: byAdmin(adminId),
  })

  assert.equal(result.outcome, 'issued')
  assert(result.outcome === 'issued')
  assert.equal(result.fulfillment.status, 'refunded')
})

test('an order whose every fulfillment is reversed is refunded', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context, db } = world

  const sellerId = await createSeller(context)
  const buyerId = await createCustomer(context)
  const listing = await createListing(context, sellerId)
  const order = await paidOrder(context, buyerId, [listing.id])

  await declineFulfillment(context, {
    fulfillmentId: await onlyFulfillmentId(db, order.id),
    sellerId,
    reason: REASON,
  })

  assert.deepEqual(await readOrderStatus(db, order.id), { status: 'refunded', refundedCents: 45_000 })
})

test('a mixed order rolls up from the half that is still live', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context, db } = world

  const declining = await createSeller(context, 'Blue Kiln Studio')
  const shipping = await createSeller(context, 'Red Barn Press')
  const buyerId = await createCustomer(context)
  const declined = await createListing(context, declining)
  const kept = await createListing(context, shipping)
  const order = await paidOrder(context, buyerId, [declined.id, kept.id])

  await declineFulfillment(context, {
    fulfillmentId: await fulfillmentIdOf(db, order.id, declining),
    sellerId: declining,
    reason: REASON,
  })
  await markShipped(context, {
    fulfillmentId: await fulfillmentIdOf(db, order.id, shipping),
    carrier: 'USPS',
    trackingNumber: '9400111899',
  })

  assert.deepEqual(await readOrderStatus(db, order.id), { status: 'shipped', refundedCents: 45_000 })
})

test('a decline takes the seller\'s held escrow back to zero', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context, db } = world

  const sellerId = await createSeller(context)
  const buyerId = await createCustomer(context)
  const listing = await createListing(context, sellerId)
  const order = await paidOrder(context, buyerId, [listing.id])
  assert.equal((await sellerBalance(context, sellerId)).heldCents, 40_500)

  await declineFulfillment(context, {
    fulfillmentId: await onlyFulfillmentId(db, order.id),
    sellerId,
    reason: REASON,
  })

  const balance = await sellerBalance(context, sellerId)
  assert.equal(balance.heldCents, 0)
  assert.equal(balance.availableCents, 0)
})

test('a refund after delivery drops the available balance', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context, db } = world

  const adminId = await createAdmin(context)
  const { sellerId, fulfillmentId } = await shippedSale(context, db)
  await confirmDelivered(context, fulfillmentId)
  assert.equal((await sellerBalance(context, sellerId)).availableCents, 40_500)

  await issueRefund(context, {
    fulfillmentId,
    reason: 'It arrived broken.',
    issuedBy: byAdmin(adminId),
  })

  const balance = await sellerBalance(context, sellerId)
  assert.equal(balance.heldCents, 0)
  assert.equal(balance.availableCents, 0)
})

test('a refund after payout carries a negative balance against the seller', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context, db } = world

  const adminId = await createAdmin(context)
  const { sellerId, fulfillmentId } = await shippedSale(context, db)
  await confirmDelivered(context, fulfillmentId)
  const payouts = await runWeeklyPayout(context, new Date('2026-08-24T09:00:00.000Z'))
  assert.equal(payouts.length, 1)

  await issueRefund(context, {
    fulfillmentId,
    reason: 'It arrived broken.',
    issuedBy: byAdmin(adminId),
  })

  const balance = await sellerBalance(context, sellerId)
  assert.equal(balance.availableCents, -40_500)
  assert.equal(balance.paidOutCents, 40_500)
})

test('a payout of a negative balance writes no row and carries the negative forward', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context, db } = world

  const adminId = await createAdmin(context)
  const { sellerId, fulfillmentId } = await shippedSale(context, db)
  await confirmDelivered(context, fulfillmentId)
  await runWeeklyPayout(context, new Date('2026-08-24T09:00:00.000Z'))
  await issueRefund(context, {
    fulfillmentId,
    reason: 'It arrived broken.',
    issuedBy: byAdmin(adminId),
  })

  const nextWeek = await runWeeklyPayout(context, new Date('2026-08-31T09:00:00.000Z'))

  assert.deepEqual(nextWeek, [])
  assert.equal((await sellerBalance(context, sellerId)).availableCents, -40_500)
})

test('a seller cannot decline after shipping', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context, db } = world

  const { sellerId, fulfillmentId, order } = await shippedSale(context, db)

  const result = await declineFulfillment(context, { fulfillmentId, sellerId, reason: REASON })

  assert.deepEqual(result, {
    outcome: 'refused',
    reason: 'illegal_transition',
    data: { fulfillment_id: fulfillmentId, order_id: order.id, status_from: 'shipped', status_to: 'declined' },
  })
  assert.equal(await readFulfillmentStatus(db, fulfillmentId), 'shipped')
  assert.equal(await refundCount(db, fulfillmentId), 0)
})

test('a seller cannot ship after declining', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context, db } = world

  const sellerId = await createSeller(context)
  const buyerId = await createCustomer(context)
  const listing = await createListing(context, sellerId)
  const order = await paidOrder(context, buyerId, [listing.id])
  const fulfillmentId = await onlyFulfillmentId(db, order.id)
  await declineFulfillment(context, { fulfillmentId, sellerId, reason: REASON })
  const refundsBefore = await refundCount(db, fulfillmentId)

  const result = await markShipped(context, { fulfillmentId, carrier: 'USPS', trackingNumber: '9400111899' })

  assert.deepEqual(result, {
    outcome: 'refused',
    reason: 'illegal_transition',
    data: { fulfillment_id: fulfillmentId, status_from: 'declined', status_to: 'shipped' },
  })
  assert.equal(await readFulfillmentStatus(db, fulfillmentId), 'declined')
  assert.equal(await refundCount(db, fulfillmentId), refundsBefore)
})

test('a fulfillment cannot be refunded twice', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context, db } = world

  const adminId = await createAdmin(context)
  const { fulfillmentId, order } = await shippedSale(context, db)
  await issueRefund(context, { fulfillmentId, reason: 'Broken.', issuedBy: byAdmin(adminId) })
  const refundsBefore = await refundCount(db, fulfillmentId)

  const result = await issueRefund(context, { fulfillmentId, reason: 'Broken again.', issuedBy: byAdmin(adminId) })

  assert.deepEqual(result, {
    outcome: 'refused',
    reason: 'illegal_transition',
    data: { fulfillment_id: fulfillmentId, order_id: order.id, status_from: 'refunded', status_to: 'refunded' },
  })
  assert.equal(await readFulfillmentStatus(db, fulfillmentId), 'refunded')
  assert.equal(await refundCount(db, fulfillmentId), refundsBefore)
})

test('a declined fulfillment cannot then be refunded', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context, db } = world

  const adminId = await createAdmin(context)
  const sellerId = await createSeller(context)
  const buyerId = await createCustomer(context)
  const listing = await createListing(context, sellerId)
  const order = await paidOrder(context, buyerId, [listing.id])
  const fulfillmentId = await onlyFulfillmentId(db, order.id)
  await declineFulfillment(context, { fulfillmentId, sellerId, reason: REASON })
  const refundsBefore = await refundCount(db, fulfillmentId)

  const result = await issueRefund(context, { fulfillmentId, reason: 'Also broken.', issuedBy: byAdmin(adminId) })

  assert.deepEqual(result, {
    outcome: 'refused',
    reason: 'illegal_transition',
    data: { fulfillment_id: fulfillmentId, order_id: order.id, status_from: 'declined', status_to: 'refunded' },
  })
  assert.equal(await readFulfillmentStatus(db, fulfillmentId), 'declined')
  assert.equal(await refundCount(db, fulfillmentId), refundsBefore)
})

test('an unpaid order has no fulfillment to refund', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context, db } = world

  const sellerId = await createSeller(context)
  const buyerId = await createCustomer(context)
  const listing = await createListing(context, sellerId)
  const order = await placedOrder(context, buyerId, [listing.id])
  const fulfillmentId = await onlyFulfillmentId(db, order.id)

  const result = await declineFulfillment(context, { fulfillmentId, sellerId, reason: REASON })

  assert.deepEqual(result, {
    outcome: 'refused',
    reason: 'order_unpaid',
    data: { fulfillment_id: fulfillmentId, order_id: order.id },
  })
  assert.equal(await readFulfillmentStatus(db, fulfillmentId), 'awaiting_shipment')
  assert.equal(await refundCount(db, fulfillmentId), 0)
})

test('a decline tells the customer, and a platform refund tells both sides', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context, db } = world

  const adminId = await createAdmin(context)
  const declining = await createSeller(context, 'Blue Kiln Studio')
  const buyerId = await createCustomer(context)
  const listing = await createListing(context, declining)
  const order = await paidOrder(context, buyerId, [listing.id])
  await declineFulfillment(context, {
    fulfillmentId: await onlyFulfillmentId(db, order.id),
    sellerId: declining,
    reason: REASON,
  })

  const refunded = await shippedSale(context, db)
  await issueRefund(context, {
    fulfillmentId: refunded.fulfillmentId,
    reason: 'It arrived broken.',
    issuedBy: byAdmin(adminId),
  })

  const subjects = await db
    .selectFrom('notifications')
    .select(['subject', 'customerId', 'sellerId'])
    .orderBy('createdAt')
    .orderBy('id')
    .execute()

  assert.deepEqual(
    subjects.filter((row) => row.subject === 'Order declined').map((row) => row.customerId),
    [buyerId],
  )
  assert.equal(subjects.filter((row) => row.subject === 'Order refunded').length, 2)
  assert.equal(
    subjects.some((row) => row.subject === 'Order refunded' && row.sellerId === refunded.sellerId),
    true,
  )
})

test('a refund names the approved charge it goes back against', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context, db } = world

  const sellerId = await createSeller(context)
  const buyerId = await createCustomer(context)
  const listing = await createListing(context, sellerId)
  const order = await paidOrder(context, buyerId, [listing.id])

  const declineResult = await declineFulfillment(context, {
    fulfillmentId: await onlyFulfillmentId(db, order.id),
    sellerId,
    reason: REASON,
  })

  assert.equal(declineResult.outcome, 'issued')
  assert(declineResult.outcome === 'issued')
  const { refund } = declineResult

  const payment = await db
    .selectFrom('payments')
    .select('id')
    .where('orderId', '=', order.id)
    .where('status', '=', 'approved')
    .executeTakeFirstOrThrow()

  assert.equal(refund.paymentId, payment.id)
})

test('the ledger records the reversal as a negative refunded entry', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context, db } = world

  const sellerId = await createSeller(context)
  const buyerId = await createCustomer(context)
  const listing = await createListing(context, sellerId)
  const order = await paidOrder(context, buyerId, [listing.id])

  await declineFulfillment(context, {
    fulfillmentId: await onlyFulfillmentId(db, order.id),
    sellerId,
    reason: REASON,
  })

  const entry = await db
    .selectFrom('ledgerEntries')
    .selectAll()
    .where('entryType', '=', 'refunded')
    .executeTakeFirstOrThrow()

  assert.equal(entry.amountCents, -40_500)
  assert.equal(entry.sellerId, sellerId)
  assert.equal(entry.payoutId, null)
})
