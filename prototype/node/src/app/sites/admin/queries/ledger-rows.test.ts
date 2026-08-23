import { test } from 'node:test'
import assert from 'node:assert/strict'
import { ledgerRows } from './ledger-rows.ts'
import { confirmDelivered } from '../../../actions/fulfillments/confirm-delivered.ts'
import { markShipped } from '../../../actions/fulfillments/mark-shipped.ts'
import { runWeeklyPayout } from '../../../actions/escrow/run-weekly-payout.ts'
import {
  createCustomer,
  createListing,
  createSeller,
  openCommerceWorld,
  paidOrder,
} from '../../../test/commerce-world.ts'

test('no entries yet is an empty list with a zeroed fold', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  assert.deepEqual(await ledgerRows(world.context), {
    rows: [],
    totals: { heldCents: 0, availableCents: 0, paidOutCents: 0 },
  })
})

test('a paid order writes one held entry naming the seller, with no fulfillment or payout link before it settles', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const sellerId = await createSeller(world.context, 'Blue Kiln Studio')
  const customerId = await createCustomer(world.context)
  const listing = await createListing(world.context, sellerId, { priceCents: 45_000 })
  await paidOrder(world.context, customerId, [listing.id])

  const { rows, totals } = await ledgerRows(world.context)
  assert.equal(rows.length, 1)
  assert.equal(rows[0]?.sellerId, sellerId)
  assert.equal(rows[0]?.sellerName, 'Blue Kiln Studio')
  assert.equal(rows[0]?.entryType, 'held')
  assert.equal(rows[0]?.amountCents, 40_500)
  assert.notEqual(rows[0]?.fulfillmentId, null)
  assert.equal(rows[0]?.payoutId, null)
  assert.deepEqual(totals, { heldCents: 40_500, availableCents: 0, paidOutCents: 0 })
})

test('filtering by type narrows the rows and folds only what matches', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const sellerId = await createSeller(world.context, 'Blue Kiln Studio')
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
  await runWeeklyPayout(world.context, new Date('2026-08-24T12:00:00.000Z'))

  const held = await ledgerRows(world.context, { entryType: 'held' })
  assert.equal(held.rows.length, 1)
  assert.deepEqual(held.totals, { heldCents: 40_500, availableCents: 0, paidOutCents: 0 })

  const paidOut = await ledgerRows(world.context, { entryType: 'paid_out' })
  assert.equal(paidOut.rows.length, 1)
  assert.equal(paidOut.rows[0]?.amountCents, -40_500)
  assert.notEqual(paidOut.rows[0]?.payoutId, null)
  assert.equal(paidOut.rows[0]?.fulfillmentId, null)
  // `ledgerBalance` folds signed amounts; a paid_out-only slice has no offsetting
  // `released` entries, so the fold's availableCents comes out negative here.
  assert.deepEqual(paidOut.totals, { heldCents: 0, availableCents: -40_500, paidOutCents: 40_500 })
})

test('filtering by seller returns only that seller’s entries', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const customerId = await createCustomer(world.context)
  const first = await createSeller(world.context, 'Blue Kiln Studio')
  const second = await createSeller(world.context, 'Rye Press')
  const firstListing = await createListing(world.context, first, { priceCents: 45_000 })
  const secondListing = await createListing(world.context, second, { priceCents: 20_000 })

  await paidOrder(world.context, customerId, [firstListing.id, secondListing.id])

  const { rows } = await ledgerRows(world.context, { sellerId: first })
  assert.equal(rows.length, 1)
  assert.equal(rows[0]?.sellerId, first)
})
