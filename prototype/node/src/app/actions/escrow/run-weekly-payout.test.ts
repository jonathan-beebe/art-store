import { test } from 'node:test'
import assert from 'node:assert/strict'
import type {
  FulfillmentId,
  OrderId,
  SellerId,
} from '../../core/ids/entity-ids.ts'
import { fixtureId } from '../../test/fixture-ids.ts'
import { runWeeklyPayout } from './run-weekly-payout.ts'
import { confirmDelivered } from '../fulfillments/confirm-delivered.ts'
import { markShipped } from '../fulfillments/mark-shipped.ts'
import { finalizeOrder } from '../orders/finalize-order.ts'
import type { ActionContext } from '../action-context.ts'
import type { AppDatabase } from '../../db/database.ts'
import {
  APPROVED_CARD,
  createCustomer,
  createListing,
  createSeller,
  openCommerceWorld,
  placedOrder,
} from '../../test/commerce-world.ts'

test('it pays a seller whose delivery landed inside the period', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  await deliverASale(world, shop)

  const payouts = await runWeeklyPayout(context, new Date('2026-08-24T09:00:00.000Z'))

  assert.equal(payouts.length, 1)
  assert.equal(payouts[0]?.sellerId, shop)
  assert.equal(payouts[0]?.amountCents, 40_500)
  assert.equal(payouts[0]?.periodStart, '2026-08-17')
  assert.equal(payouts[0]?.periodEnd, '2026-08-23')
})

test('a payout writes a matching negative paid_out entry dated at the close of the period', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  await deliverASale(world, shop)

  const payouts = await runWeeklyPayout(context, new Date('2026-08-24T09:00:00.000Z'))
  const entry = await world.db
    .selectFrom('ledgerEntries')
    .selectAll()
    .where('entryType', '=', 'paid_out')
    .executeTakeFirstOrThrow()

  assert.equal(entry.amountCents, -40_500)
  assert.equal(entry.payoutId, payouts[0]?.id)
  assert.equal(entry.occurredAt, '2026-08-23T23:59:59.999Z')
})

test('running the same period twice pays once', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  await deliverASale(world, shop)
  await runWeeklyPayout(context, new Date('2026-08-24T09:00:00.000Z'))

  const second = await runWeeklyPayout(context, new Date('2026-08-25T09:00:00.000Z'))

  assert.deepEqual(second, [])
  const payouts = await world.db.selectFrom('payouts').select('id').execute()
  assert.equal(payouts.length, 1)
})

test('it pays each seller their own released amount', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const painter = await createSeller(context, 'Blue Kiln Studio')
  const printer = await createSeller(context, 'Rye Press')
  await deliverASale(world, painter, { priceCents: 45_000 })
  await deliverASale(world, printer, { priceCents: 10_000 })

  const payouts = await runWeeklyPayout(context, new Date('2026-08-24T09:00:00.000Z'))

  const bySeller = new Map(payouts.map((payout) => [payout.sellerId, payout.amountCents]))
  assert.equal(bySeller.get(painter), 40_500)
  assert.equal(bySeller.get(printer), 9_000)
})

test('money still held in escrow is not paid out', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  const buyer = await createCustomer(context)
  const art = await createListing(context, shop)
  const order = await placedOrder(context, buyer, [art.id])
  await finalizeOrder(context, { orderId: order.id, cardNumber: APPROVED_CARD })

  const payouts = await runWeeklyPayout(context, new Date('2026-08-24T09:00:00.000Z'))

  assert.deepEqual(payouts, [])
  const rows = await world.db.selectFrom('payouts').select('id').execute()
  assert.equal(rows.length, 0)
})

test('a delivery after the period ends waits for the next run', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  await deliverASale(world, shop, { deliveredAt: new Date('2026-08-24T11:00:00.000Z') })

  const payouts = await runWeeklyPayout(context, new Date('2026-08-24T12:00:00.000Z'))

  assert.deepEqual(payouts, [])
})

test('money released into a period that was already settled waits for the next run', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  await deliverASale(world, shop)

  // Settling a week before it closes leaves room for another delivery to land
  // inside it.
  await runWeeklyPayout(context, new Date('2026-08-24T09:00:00.000Z'))
  await deliverASale(world, shop, { priceCents: 20_000 })

  const rerun = await runWeeklyPayout(context, new Date('2026-08-24T09:00:00.000Z'))
  assert.deepEqual(rerun, [])

  const next = await runWeeklyPayout(context, new Date('2026-08-31T09:00:00.000Z'))
  assert.equal(next.length, 1)
  assert.equal(next[0]?.amountCents, 18_000)
  assert.equal(next[0]?.periodStart, '2026-08-24')
})

const SHIPPED_AT = new Date('2026-08-20T11:00:00.000Z')

/** A sale delivered inside the week ending 2026-08-23, ready for the payout run. */
async function deliverASale(
  world: { context: ActionContext; db: AppDatabase; travelTo(instant: Date): void },
  sellerId: SellerId,
  options: { priceCents?: number; deliveredAt?: Date } = {},
): Promise<void> {
  const { context } = world
  const buyer = await createCustomer(context)
  const art = await createListing(context, sellerId, { priceCents: options.priceCents ?? 45_000 })
  const order = await placedOrder(context, buyer, [art.id])
  await finalizeOrder(context, { orderId: order.id, cardNumber: APPROVED_CARD })
  const [fulfillmentId] = await fulfillmentIds(world.db, order.id)

  world.travelTo(SHIPPED_AT)
  await markShipped(context, { fulfillmentId: fulfillmentId ?? fixtureId('ful', 0), carrier: 'USPS', trackingNumber: '9400111899' })

  world.travelTo(options.deliveredAt ?? new Date('2026-08-21T11:00:00.000Z'))
  await confirmDelivered(context, fulfillmentId ?? fixtureId('ful', 0))
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
