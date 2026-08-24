import { test } from 'node:test'
import assert from 'node:assert/strict'
import type { ListingId, OrderId } from '../../core/ids/entity-ids.ts'
import { finalizeOrder } from './finalize-order.ts'
import type { DeliveryContext } from '../../delivery/delivery-context.ts'
import type { DeliverableNotification } from '../../delivery/notification-delivery.ts'
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

test('an approved card pays the order and stamps finalizedAt', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  const buyer = await createCustomer(context)
  const art = await createListing(context, shop)
  const order = await placedOrder(context, buyer, [art.id])

  const paid = await finalizeOrder(context, { orderId: order.id, cardNumber: APPROVED_CARD })

  assert.equal(paid.status, 'paid')
  assert.notEqual(paid.finalizedAt, null)
})

test('it records one approved payments row with the amount and last four and a null decline reason', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  const buyer = await createCustomer(context)
  const art = await createListing(context, shop, { priceCents: 45_000 })
  const order = await placedOrder(context, buyer, [art.id])

  await finalizeOrder(context, { orderId: order.id, cardNumber: APPROVED_CARD })

  assert.deepEqual(await readPayments(world.db, order.id), [
    { status: 'approved', amountCents: 45_000, cardLastFour: '4242', declineReason: null },
  ])
})

test("a paid order holds each seller's net in escrow", async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  const buyer = await createCustomer(context)
  const art = await createListing(context, shop, { priceCents: 45_000 })
  const order = await placedOrder(context, buyer, [art.id])

  const paid = await finalizeOrder(context, { orderId: order.id, cardNumber: APPROVED_CARD })
  const [fulfillment] = await readFulfillments(world.db, paid.id)
  const [entry] = await readLedgerEntries(world.db)

  assert.equal(entry?.entryType, 'held')
  assert.equal(entry?.amountCents, 40_500)
  assert.equal(entry?.sellerId, shop)
  assert.equal(entry?.fulfillmentId, fulfillment?.id)
})

test('a two-seller order holds one amount per seller', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const painter = await createSeller(context, 'Blue Kiln Studio')
  const printer = await createSeller(context, 'Rye Press')
  const buyer = await createCustomer(context)
  const painting = await createListing(context, painter, { priceCents: 45_000 })
  const print = await createListing(context, printer, { priceCents: 10_000 })
  const order = await placedOrder(context, buyer, [painting.id, print.id])

  await finalizeOrder(context, { orderId: order.id, cardNumber: APPROVED_CARD })

  const amounts = (await readLedgerEntries(world.db)).map((entry) => entry.amountCents).sort((a, b) => a - b)
  assert.deepEqual(amounts, [9_000, 40_500])
})

test('it tells each seller their item sold', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  const buyer = await createCustomer(context)
  const art = await createListing(context, shop, { priceCents: 45_000 })
  const order = await placedOrder(context, buyer, [art.id])

  await finalizeOrder(context, { orderId: order.id, cardNumber: APPROVED_CARD })

  const [notification] = await readNotifications(world.db)
  assert.equal(notification?.sellerId, shop)
  assert.equal(notification?.subject, 'Item sold')
  assert.match(notification?.body ?? '', /\$405\.00/)
})

test('a declined card fails the payment with the right decline reason and no finalizedAt', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  const buyer = await createCustomer(context)
  const art = await createListing(context, shop)
  const order = await placedOrder(context, buyer, [art.id])

  const declined = await finalizeOrder(context, { orderId: order.id, cardNumber: DECLINED_CARD })

  assert.equal(declined.status, 'payment_failed')
  assert.equal(declined.finalizedAt, null)
  const [payment] = await readPayments(world.db, order.id)
  assert.equal(payment?.declineReason, 'generic_decline')
})

test('a declined card puts the stock back on the storefront', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  const buyer = await createCustomer(context)
  const art = await createListing(context, shop, { quantity: 1 })
  const order = await placedOrder(context, buyer, [art.id])

  await finalizeOrder(context, { orderId: order.id, cardNumber: DECLINED_CARD })

  assert.deepEqual(await readStock(world.db, art.id), { quantity: 1, status: 'for_sale' })
})

test('a declined card holds nothing and tells nobody', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  const buyer = await createCustomer(context)
  const art = await createListing(context, shop)
  const order = await placedOrder(context, buyer, [art.id])

  await finalizeOrder(context, { orderId: order.id, cardNumber: DECLINED_CARD })

  assert.deepEqual(await readLedgerEntries(world.db), [])
  assert.deepEqual(await readNotifications(world.db), [])
})

test('it refuses to charge an order that is already paid', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  const buyer = await createCustomer(context)
  const art = await createListing(context, shop)
  const order = await placedOrder(context, buyer, [art.id])
  await finalizeOrder(context, { orderId: order.id, cardNumber: APPROVED_CARD })

  await assert.rejects(
    () => finalizeOrder(context, { orderId: order.id, cardNumber: APPROVED_CARD }),
    /cannot move from paid to paid/,
  )
})

test('it refuses to charge an order that has not been verified', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  const guest = await createCustomer(context, { isVerified: false })
  const art = await createListing(context, shop)
  const order = await placedOrder(context, guest, [art.id], { isVerified: false })

  await assert.rejects(
    () => finalizeOrder(context, { orderId: order.id, cardNumber: APPROVED_CARD }),
    /cannot move from pending_verification to paid/,
  )
})

test('a notificationDelivery passed on the context receives the delivered notification', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  const buyer = await createCustomer(context)
  const art = await createListing(context, shop)
  const order = await placedOrder(context, buyer, [art.id])

  const delivered: DeliverableNotification[] = []
  const notificationDelivery = {
    deliver: async (_context: DeliveryContext, n: DeliverableNotification) => {
      delivered.push(n)
    },
  }

  await finalizeOrder({ ...context, notificationDelivery }, { orderId: order.id, cardNumber: APPROVED_CARD })

  assert.equal(delivered.length, 1)
  assert.equal(delivered[0]?.recipientType, 'seller')
  assert.equal(delivered[0]?.recipientId, shop)
  assert.equal(delivered[0]?.subject, 'Item sold')
})

async function readPayments(db: AppDatabase, orderId: OrderId) {
  return db
    .selectFrom('payments')
    .select(['status', 'amountCents', 'cardLastFour', 'declineReason'])
    .where('orderId', '=', orderId)
    .execute()
}

async function readFulfillments(db: AppDatabase, orderId: OrderId) {
  return db.selectFrom('fulfillments').select(['id', 'sellerId']).where('orderId', '=', orderId).execute()
}

async function readLedgerEntries(db: AppDatabase) {
  return db.selectFrom('ledgerEntries').selectAll().execute()
}

async function readNotifications(db: AppDatabase) {
  return db.selectFrom('notifications').selectAll().execute()
}

async function readStock(db: AppDatabase, listingId: ListingId) {
  return db
    .selectFrom('listings')
    .select(['quantity', 'status'])
    .where('id', '=', listingId)
    .executeTakeFirstOrThrow()
}
