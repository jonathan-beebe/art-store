import { test } from 'node:test'
import assert from 'node:assert/strict'
import { platformMoney } from './platform-money.ts'
import { confirmDelivered } from '../../../actions/fulfillments/confirm-delivered.ts'
import { markShipped } from '../../../actions/fulfillments/mark-shipped.ts'
import { runWeeklyPayout } from '../../../actions/escrow/run-weekly-payout.ts'
import {
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
  })

  await runWeeklyPayout(world.context, new Date('2026-08-24T12:00:00.000Z'))

  assert.deepEqual(await platformMoney(world.context), {
    heldCents: 0,
    availableCents: 0,
    paidOutCents: 40_500,
    feesEarnedCents: 4_500,
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
  })
})
