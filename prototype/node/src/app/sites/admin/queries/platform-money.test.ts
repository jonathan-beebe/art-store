import { test } from 'node:test'
import assert from 'node:assert/strict'
import { platformMoney } from './platform-money.ts'
import { confirmDelivered } from '../../../actions/fulfillments/confirm-delivered.ts'
import { declineFulfillment } from '../../../actions/fulfillments/decline-fulfillment.ts'
import { markShipped } from '../../../actions/fulfillments/mark-shipped.ts'
import { issueRefund } from '../../../actions/refunds/issue-refund.ts'
import { runWeeklyPayout } from '../../../actions/escrow/run-weekly-payout.ts'
import { feeTotals } from '../../../core/escrow/fee-totals.ts'
import { FULFILLMENT_STATUSES } from '../../../core/orders/fulfillment-status.ts'
import {
  createAdmin,
  createCustomer,
  createListing,
  createSeller,
  openCommerceWorld,
  paidOrder,
  placedOrder,
} from '../../../test/commerce-world.ts'

test('an empty platform holds, owes, and has earned nothing', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  assert.deepEqual(await platformMoney(world.context), {
    heldCents: 0,
    availableCents: 0,
    paidOutCents: 0,
    feesEarnedCents: 0,
    feesRefundedCents: 0,
    refundedCents: 0,
  })
})

test('a paid order holds the net and earns the fee', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const sellerId = await createSeller(world.context)
  const customerId = await createCustomer(world.context)
  const listing = await createListing(world.context, sellerId, { priceCents: 45_000 })

  await paidOrder(world.context, customerId, [listing.id])

  assert.deepEqual(await platformMoney(world.context), {
    heldCents: 40_500,
    availableCents: 0,
    paidOutCents: 0,
    feesEarnedCents: 4_500,
    feesRefundedCents: 0,
    refundedCents: 0,
  })
})

test('an order nobody paid for earns no fee', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const sellerId = await createSeller(world.context)
  const customerId = await createCustomer(world.context)
  const listing = await createListing(world.context, sellerId, { priceCents: 45_000 })

  await placedOrder(world.context, customerId, [listing.id])

  assert.deepEqual(await platformMoney(world.context), {
    heldCents: 0,
    availableCents: 0,
    paidOutCents: 0,
    feesEarnedCents: 0,
    feesRefundedCents: 0,
    refundedCents: 0,
  })
})

test('delivery moves the money to available and a payout moves it out', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const sellerId = await createSeller(world.context)
  const customerId = await createCustomer(world.context)
  const listing = await createListing(world.context, sellerId, { priceCents: 45_000 })
  const order = await paidOrder(world.context, customerId, [listing.id])

  const fulfillment = await world.db
    .selectFrom('fulfillments')
    .selectAll()
    .where('orderId', '=', order.id)
    .executeTakeFirstOrThrow()

  await markShipped(world.context, {
    fulfillmentId: fulfillment.id,
    carrier: 'Royal Mail',
    trackingNumber: 'RM123',
  })
  await confirmDelivered(world.context, fulfillment.id)

  assert.deepEqual(await platformMoney(world.context), {
    heldCents: 0,
    availableCents: 40_500,
    paidOutCents: 0,
    feesEarnedCents: 4_500,
    feesRefundedCents: 0,
    refundedCents: 0,
  })

  await runWeeklyPayout(world.context, new Date('2026-08-24T12:00:00.000Z'))

  assert.deepEqual(await platformMoney(world.context), {
    heldCents: 0,
    availableCents: 0,
    paidOutCents: 40_500,
    feesEarnedCents: 4_500,
    feesRefundedCents: 0,
    refundedCents: 0,
  })
})

test('two sellers fold into one platform balance', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const customerId = await createCustomer(world.context)
  const first = await createListing(world.context, await createSeller(world.context), {
    priceCents: 45_000,
  })
  const second = await createListing(world.context, await createSeller(world.context), {
    priceCents: 20_000,
  })

  await paidOrder(world.context, customerId, [first.id, second.id])

  assert.deepEqual(await platformMoney(world.context), {
    heldCents: 58_500,
    availableCents: 0,
    paidOutCents: 0,
    feesEarnedCents: 6_500,
    feesRefundedCents: 0,
    refundedCents: 0,
  })
})

test('a refund forgoes the fee and counts what went back to the customer', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const sellerId = await createSeller(world.context)
  const customerId = await createCustomer(world.context)
  const listing = await createListing(world.context, sellerId, { priceCents: 45_000 })
  const order = await paidOrder(world.context, customerId, [listing.id])
  const fulfillment = await world.db
    .selectFrom('fulfillments')
    .selectAll()
    .where('orderId', '=', order.id)
    .executeTakeFirstOrThrow()

  await declineFulfillment(world.context, {
    fulfillmentId: fulfillment.id,
    sellerId,
    reason: 'The piece cracked in the kiln.',
  })

  assert.deepEqual(await platformMoney(world.context), {
    heldCents: 0,
    availableCents: 0,
    paidOutCents: 0,
    feesEarnedCents: 0,
    feesRefundedCents: 4_500,
    refundedCents: 45_000,
  })
})

test('fees earned and refunded equal feeTotals over every fulfillment status', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context, db } = world

  // A paid order always leaves a `held` entry, so every fulfillment built
  // this way is a subject `settledFulfillments` reads.
  async function heldFulfillment(priceCents: number) {
    const sellerId = await createSeller(context)
    const customerId = await createCustomer(context)
    const listing = await createListing(context, sellerId, { priceCents })
    const order = await paidOrder(context, customerId, [listing.id])

    return db.selectFrom('fulfillments').selectAll().where('orderId', '=', order.id).executeTakeFirstOrThrow()
  }

  await heldFulfillment(45_000) // stays awaiting_shipment

  const shipped = await heldFulfillment(30_000)
  await markShipped(context, { fulfillmentId: shipped.id, carrier: 'USPS', trackingNumber: 'A1' })

  const delivered = await heldFulfillment(20_000)
  await markShipped(context, { fulfillmentId: delivered.id, carrier: 'USPS', trackingNumber: 'A2' })
  await confirmDelivered(context, delivered.id)

  const declined = await heldFulfillment(15_000)
  await declineFulfillment(context, {
    fulfillmentId: declined.id,
    sellerId: declined.sellerId,
    reason: 'Out of stock.',
  })

  const refunded = await heldFulfillment(25_000)
  await markShipped(context, { fulfillmentId: refunded.id, carrier: 'USPS', trackingNumber: 'A3' })
  const adminId = await createAdmin(context)
  await issueRefund(context, {
    fulfillmentId: refunded.id,
    reason: 'Damaged in transit.',
    issuedBy: { type: 'admin', id: adminId },
  })

  const subjects = await db
    .selectFrom('fulfillments')
    .innerJoin('ledgerEntries', 'ledgerEntries.fulfillmentId', 'fulfillments.id')
    .where('ledgerEntries.entryType', '=', 'held')
    .select(['fulfillments.feeCents as feeCents', 'fulfillments.status as status'])
    .execute()

  assert.deepEqual(
    new Set(subjects.map((subject) => subject.status)),
    new Set(FULFILLMENT_STATUSES),
  )

  const expected = feeTotals(subjects)
  const money = await platformMoney(context)

  assert.equal(money.feesEarnedCents, expected.earnedCents)
  assert.equal(money.feesRefundedCents, expected.refundedCents)
})
