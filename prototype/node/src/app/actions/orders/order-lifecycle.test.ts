import { test } from 'node:test'
import assert from 'node:assert/strict'
import type {
  FulfillmentId,
  ListingId,
  OrderId,
  SellerId,
} from '../../core/ids/entity-ids.ts'
import { fixtureId } from '../../test/fixture-ids.ts'
import { BrokenContractError } from '../../core/defect.ts'
import { cancelOrder, cancelledOrder } from './cancel-order.ts'
import { finalizeOrder } from './finalize-order.ts'
import { markAwaitingPayment } from './mark-awaiting-payment.ts'
import { confirmDelivered } from '../fulfillments/confirm-delivered.ts'
import { markShipped } from '../fulfillments/mark-shipped.ts'
import { runWeeklyPayout } from '../escrow/run-weekly-payout.ts'
import { sellerBalance } from '../escrow/ledger-balances.ts'
import type { ActionContext } from '../action-context.ts'
import type { AppDatabase } from '../../db/database.ts'
import {
  APPROVED_CARD,
  UNFUNDED_CARD,
  createCustomer,
  createListing,
  createSeller,
  openCommerceWorld,
  placedOrder,
} from '../../test/commerce-world.ts'

const PAID_AT = new Date('2026-08-20T10:00:00.000Z')
const SHIPPED_AT = new Date('2026-08-21T11:00:00.000Z')
const DELIVERED_AT = new Date('2026-08-22T09:00:00.000Z')
const PAYOUT_RUN_AT = new Date('2026-08-24T09:00:00.000Z')

test('an order runs from the cart to the weekly payout', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const painter = await createSeller(context, 'Blue Kiln Studio')
  const printer = await createSeller(context, 'Rye Press')
  const painting = await createListing(context, painter, { priceCents: 45_000, quantity: 1 })
  const print = await createListing(context, printer, { priceCents: 12_000, quantity: 1 })
  const buyer = await createCustomer(context)

  // Placement claims the stock and prices each seller's slice once.
  const order = await placedOrder(context, buyer, [painting.id, print.id])

  assert.equal(order.status, 'awaiting_payment')
  assert.equal(order.totalCents, 57_000)
  assert.equal((await readListing(world.db, painting.id)).status, 'sold')
  assert.deepEqual(await fulfillmentMoney(world.db, order.id), [
    { sellerId: painter, subtotalCents: 45_000, feeCents: 4_500, netCents: 40_500 },
    { sellerId: printer, subtotalCents: 12_000, feeCents: 1_200, netCents: 10_800 },
  ])

  world.travelTo(PAID_AT)
  const paid = await finalizeOrder(context, { orderId: order.id, cardNumber: APPROVED_CARD })

  assert.equal(paid.status, 'paid')
  assert.equal(await countNotifications(world.db, 'Item sold'), 2)
  assert.deepEqual(await heldFor(context, [painter, printer]), [40_500, 10_800])

  const [paintingShipment, printShipment] = await fulfillmentIds(world.db, order.id)

  world.travelTo(SHIPPED_AT)
  await markShipped(context, {
    fulfillmentId: paintingShipment ?? fixtureId('ful', 0),
    carrier: 'USPS',
    trackingNumber: '9400111899',
  })

  assert.equal(await readOrderStatus(world.db, order.id), 'partially_shipped')

  await markShipped(context, {
    fulfillmentId: printShipment ?? fixtureId('ful', 0),
    carrier: 'FedEx',
    trackingNumber: '7712349',
  })

  assert.equal(await readOrderStatus(world.db, order.id), 'shipped')
  assert.equal(await countNotifications(world.db, 'Order shipped'), 2)

  // Delivery is what releases escrow, one fulfillment at a time.
  world.travelTo(DELIVERED_AT)
  await confirmDelivered(context, paintingShipment ?? fixtureId('ful', 0))

  assert.equal(await readOrderStatus(world.db, order.id), 'shipped')

  await confirmDelivered(context, printShipment ?? fixtureId('ful', 0))

  assert.equal(await readOrderStatus(world.db, order.id), 'delivered')
  assert.deepEqual(await availableFor(context, [painter, printer]), [40_500, 10_800])

  world.travelTo(PAYOUT_RUN_AT)
  const payouts = await runWeeklyPayout(context, PAYOUT_RUN_AT)

  assert.equal(payouts.length, 2)
  assert.deepEqual(
    payouts.map((payout) => [payout.sellerId, payout.amountCents, payout.periodStart, payout.periodEnd]),
    [
      [painter, 40_500, '2026-08-17', '2026-08-23'],
      [printer, 10_800, '2026-08-17', '2026-08-23'],
    ],
  )
  assert.deepEqual(await heldFor(context, [painter, printer]), [0, 0])
  assert.deepEqual(await availableFor(context, [painter, printer]), [0, 0])
  assert.deepEqual(await paidOutFor(context, [painter, printer]), [40_500, 10_800])
})

test('a declined card returns the stock and a retry completes the order', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  const art = await createListing(context, shop, { priceCents: 45_000, quantity: 1 })
  const buyer = await createCustomer(context)
  const order = await placedOrder(context, buyer, [art.id])

  world.travelTo(PAID_AT)
  const declined = await finalizeOrder(context, { orderId: order.id, cardNumber: UNFUNDED_CARD })

  assert.equal(declined.status, 'payment_failed')
  assert.equal(declined.finalizedAt, null)
  assert.deepEqual(await readStock(world.db, art.id), { quantity: 1, status: 'for_sale' })
  assert.equal(await countLedgerEntries(world.db), 0)
  assert.equal(await countNotifications(world.db, 'Item sold'), 0)

  const retried = await finalizeOrder(context, { orderId: order.id, cardNumber: APPROVED_CARD })

  assert.equal(retried.status, 'paid')
  assert.deepEqual(await readStock(world.db, art.id), { quantity: 0, status: 'sold' })
  assert.deepEqual(await paymentAttempts(world.db, order.id), [
    { status: 'declined', declineReason: 'insufficient_funds' },
    { status: 'approved', declineReason: null },
  ])
  assert.deepEqual(await heldFor(context, [shop]), [40_500])

  const [fulfillment] = await fulfillmentIds(world.db, order.id)
  world.travelTo(SHIPPED_AT)
  await markShipped(context, {
    fulfillmentId: fulfillment ?? fixtureId('ful', 0),
    carrier: 'USPS',
    trackingNumber: '9400111899',
  })
  world.travelTo(DELIVERED_AT)
  await confirmDelivered(context, fulfillment ?? fixtureId('ful', 0))

  world.travelTo(PAYOUT_RUN_AT)
  const payouts = await runWeeklyPayout(context, PAYOUT_RUN_AT)

  assert.deepEqual(
    payouts.map((payout) => payout.amountCents),
    [40_500],
  )
  assert.deepEqual(await paidOutFor(context, [shop]), [40_500])
})

test('cancelling an unpaid order hands the stock back to the storefront', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  const art = await createListing(context, shop, { quantity: 1 })
  const guest = await createCustomer(context, { isVerified: false })
  const order = await placedOrder(context, guest, [art.id], { isVerified: false })

  assert.equal(order.status, 'pending_verification')
  assert.deepEqual(await readStock(world.db, art.id), { quantity: 0, status: 'sold' })

  const cancelled = cancelledOrder(await cancelOrder(context, order.id))

  assert.equal(cancelled.status, 'cancelled')
  assert.notEqual(cancelled.cancelledAt, null)
  assert.deepEqual(await readStock(world.db, art.id), { quantity: 1, status: 'for_sale' })
})

test('a cancelled order cannot be verified, charged, or cancelled again', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  const art = await createListing(context, shop, { quantity: 1 })
  const buyer = await createCustomer(context)
  const order = await placedOrder(context, buyer, [art.id])
  await cancelOrder(context, order.id)

  await assert.rejects(
    () => finalizeOrder(context, { orderId: order.id, cardNumber: APPROVED_CARD }),
    (error: unknown) => error instanceof BrokenContractError && /cannot move from cancelled to paid/.test(error.message),
  )
  assert.deepEqual(await cancelOrder(context, order.id), {
    outcome: 'refused',
    reason: 'illegal_transition',
    data: { order_id: order.id, status_from: 'cancelled', status_to: 'cancelled' },
  })
  assert.equal((await markAwaitingPayment(context, order.id)).status, 'cancelled')
})

async function readListing(db: AppDatabase, listingId: ListingId) {
  return db.selectFrom('listings').selectAll().where('id', '=', listingId).executeTakeFirstOrThrow()
}

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

async function fulfillmentIds(db: AppDatabase, orderId: OrderId): Promise<FulfillmentId[]> {
  const rows = await db
    .selectFrom('fulfillments')
    .select('id')
    .where('orderId', '=', orderId)
    .orderBy('sellerId')
    .execute()

  return rows.map((row) => row.id)
}

async function fulfillmentMoney(db: AppDatabase, orderId: OrderId) {
  return db
    .selectFrom('fulfillments')
    .select(['sellerId', 'subtotalCents', 'feeCents', 'netCents'])
    .where('orderId', '=', orderId)
    .orderBy('sellerId')
    .execute()
}

async function paymentAttempts(db: AppDatabase, orderId: OrderId) {
  return db
    .selectFrom('payments')
    .select(['status', 'declineReason'])
    .where('orderId', '=', orderId)
    .orderBy('id')
    .execute()
}

async function countLedgerEntries(db: AppDatabase): Promise<number> {
  const rows = await db.selectFrom('ledgerEntries').select('id').execute()
  return rows.length
}

async function countNotifications(db: AppDatabase, subject: string): Promise<number> {
  const rows = await db.selectFrom('notifications').select('id').where('subject', '=', subject).execute()
  return rows.length
}

async function heldFor(context: ActionContext, sellerIds: readonly SellerId[]): Promise<number[]> {
  return Promise.all(sellerIds.map(async (id) => (await sellerBalance(context, id)).heldCents))
}

async function availableFor(context: ActionContext, sellerIds: readonly SellerId[]): Promise<number[]> {
  return Promise.all(sellerIds.map(async (id) => (await sellerBalance(context, id)).availableCents))
}

async function paidOutFor(context: ActionContext, sellerIds: readonly SellerId[]): Promise<number[]> {
  return Promise.all(sellerIds.map(async (id) => (await sellerBalance(context, id)).paidOutCents))
}
