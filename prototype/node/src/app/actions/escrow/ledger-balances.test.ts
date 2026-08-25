import { test } from 'node:test'
import assert from 'node:assert/strict'
import type { FulfillmentId, OrderId, SellerId } from '../../core/ids/entity-ids.ts'
import type { LedgerEntryType } from '../../core/escrow/ledger-entry-type.ts'
import { ledgerBalance, ledgerBalancesBySeller } from '../../core/escrow/ledger-balance.ts'
import { newId } from '../../ids.ts'
import { ledgerMovements } from './ledger-movements.ts'
import { platformBalance, sellerBalance, sellerBalances } from './ledger-balances.ts'
import { createCustomer, createListing, createSeller, openCommerceWorld, paidOrder } from '../../test/commerce-world.ts'

test('an empty ledger is all zeroes', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)

  const balance = await sellerBalance(context, shop)

  assert.deepEqual(balance, { heldCents: 0, availableCents: 0, paidOutCents: 0 })
})

test('a hold shows as held and is not payable', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  await insertLedgerEntry(world, shop, 'held', 40_500, '2026-08-20T10:00:00.000Z')

  const balance = await sellerBalance(context, shop)

  assert.equal(balance.heldCents, 40_500)
  assert.equal(balance.availableCents, 0)
})

test('a release moves it to available', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  await insertLedgerEntry(world, shop, 'held', 40_500, '2026-08-20T10:00:00.000Z')
  await insertLedgerEntry(world, shop, 'released', 40_500, '2026-08-22T09:00:00.000Z')

  const balance = await sellerBalance(context, shop)

  assert.equal(balance.heldCents, 0)
  assert.equal(balance.availableCents, 40_500)
})

test('a payout empties available and shows as paid out', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  await insertLedgerEntry(world, shop, 'held', 40_500, '2026-08-20T10:00:00.000Z')
  await insertLedgerEntry(world, shop, 'released', 40_500, '2026-08-22T09:00:00.000Z')
  await insertLedgerEntry(world, shop, 'paid_out', -40_500, '2026-08-23T23:59:59.999Z')

  const balance = await sellerBalance(context, shop)

  assert.equal(balance.availableCents, 0)
  assert.equal(balance.paidOutCents, 40_500)
})

test('bounding with occurredBy excludes a later entry', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  await insertLedgerEntry(world, shop, 'held', 40_500, '2026-08-20T10:00:00.000Z')
  await insertLedgerEntry(world, shop, 'released', 40_500, '2026-08-25T09:00:00.000Z')

  const balance = await sellerBalance(context, shop, '2026-08-23T23:59:59.999Z')

  assert.equal(balance.heldCents, 40_500)
  assert.equal(balance.availableCents, 0)
})

test('another seller’s movements never reach this seller’s balance', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context, 'Blue Kiln Studio')
  const otherShop = await createSeller(context, 'Rye Press')
  await insertLedgerEntry(world, shop, 'held', 40_500, '2026-08-20T10:00:00.000Z')
  await insertLedgerEntry(world, otherShop, 'held', 9_000, '2026-08-20T10:00:00.000Z')

  const balance = await sellerBalance(context, shop)

  assert.equal(balance.heldCents, 40_500)
})

test('a refund before release comes out of held, leaving other held money alone', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  const refunded = await heldFulfillment(world, shop)
  await heldFulfillment(world, shop)
  await insertLedgerEntry(world, shop, 'refunded', -40_500, '2026-08-21T09:00:00.000Z', refunded)

  const balance = await sellerBalance(context, shop)

  assert.equal(balance.heldCents, 40_500)
  assert.equal(balance.availableCents, 0)
})

test('a refund landing after payout drives available negative', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  const fulfillmentId = await heldFulfillment(world, shop)
  await insertLedgerEntry(world, shop, 'released', 40_500, '2026-08-21T09:00:00.000Z', fulfillmentId)
  await insertLedgerEntry(world, shop, 'paid_out', -40_500, '2026-08-22T09:00:00.000Z')
  await insertLedgerEntry(world, shop, 'refunded', -40_500, '2026-08-23T09:00:00.000Z', fulfillmentId)

  const balance = await sellerBalance(context, shop)

  assert.equal(balance.heldCents, 0)
  assert.equal(balance.availableCents, -40_500)
  assert.equal(balance.paidOutCents, 40_500)
})

test('a refund with no fulfillment comes out of held', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  await heldFulfillment(world, shop)
  await insertLedgerEntry(world, shop, 'refunded', -10_000, '2026-08-21T09:00:00.000Z', null)

  const balance = await sellerBalance(context, shop)

  assert.equal(balance.heldCents, 30_500)
  assert.equal(balance.availableCents, 0)
})

test('an occurredBy cutoff reads a not-yet-released refund out of held; without it, out of available', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  const fulfillmentId = await heldFulfillment(world, shop)
  await insertLedgerEntry(world, shop, 'refunded', -15_000, '2026-08-21T09:00:00.000Z', fulfillmentId)
  await insertLedgerEntry(world, shop, 'released', 40_500, '2026-08-25T09:00:00.000Z', fulfillmentId)

  const asOfCutoff = await sellerBalance(context, shop, '2026-08-23T23:59:59.999Z')
  assert.equal(asOfCutoff.heldCents, 25_500)
  assert.equal(asOfCutoff.availableCents, 0)

  const unbounded = await sellerBalance(context, shop)
  assert.equal(unbounded.heldCents, 0)
  assert.equal(unbounded.availableCents, 25_500)
})

test('sellerBalances matches ledgerBalancesBySeller over the same read', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  await seedMixedLedger(world)

  assert.deepEqual(await sellerBalances(context), ledgerBalancesBySeller(await ledgerMovements(context)))
})

test('platformBalance matches ledgerBalance over the same read', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  await seedMixedLedger(world)

  assert.deepEqual(await platformBalance(context), ledgerBalance(await ledgerMovements(context)))
})

test('sellerBalances bound by occurredBy matches ledgerBalancesBySeller bound the same way', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const { cutoff } = await seedMixedLedger(world)

  assert.deepEqual(
    await sellerBalances(context, cutoff),
    ledgerBalancesBySeller(await ledgerMovements(context, cutoff)),
  )
})

test('sellerBalances omits a seller with no ledger entries', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const active = await createSeller(context, 'Blue Kiln Studio')
  const idle = await createSeller(context, 'Rye Press')
  await insertLedgerEntry(world, active, 'held', 40_500, '2026-08-20T10:00:00.000Z')

  const balances = await sellerBalances(context)

  assert.equal(balances.size, 1)
  assert.equal(balances.has(idle), false)
  assert.deepEqual(balances.get(active), { heldCents: 40_500, availableCents: 0, paidOutCents: 0 })
})

async function insertLedgerEntry(
  world: Awaited<ReturnType<typeof openCommerceWorld>>,
  sellerId: SellerId,
  entryType: LedgerEntryType,
  amountCents: number,
  occurredAt: string,
  fulfillmentId: FulfillmentId | null = null,
): Promise<void> {
  await world.db
    .insertInto('ledgerEntries')
    .values({ id: newId('led', new Date()), sellerId, fulfillmentId, payoutId: null, entryType, amountCents, occurredAt })
    .execute()
}

async function onlyFulfillmentId(
  world: Awaited<ReturnType<typeof openCommerceWorld>>,
  orderId: OrderId,
): Promise<FulfillmentId> {
  const row = await world.db
    .selectFrom('fulfillments')
    .select('id')
    .where('orderId', '=', orderId)
    .executeTakeFirstOrThrow()

  return row.id
}

/** A fulfillment with a real `held` entry — the id a synthetic release or refund can reference under the foreign key. */
async function heldFulfillment(
  world: Awaited<ReturnType<typeof openCommerceWorld>>,
  sellerId: SellerId,
): Promise<FulfillmentId> {
  const { context } = world
  const buyerId = await createCustomer(context)
  const listing = await createListing(context, sellerId)
  const order = await paidOrder(context, buyerId, [listing.id])

  return onlyFulfillmentId(world, order.id)
}

type MixedLedgerSeed = { sellerA: SellerId; sellerB: SellerId; sellerC: SellerId; cutoff: string }

/**
 * Three sellers exercising every entry type and both refund buckets: seller A
 * holds, releases, and is paid out; seller B is refunded before release;
 * seller C is refunded after release, and again with no fulfillment at all.
 * `cutoff` lands after A's release and B's refund but before A's payout and
 * C's second refund, so a bound and an unbound read diverge.
 */
async function seedMixedLedger(
  world: Awaited<ReturnType<typeof openCommerceWorld>>,
): Promise<MixedLedgerSeed> {
  const { context } = world
  const sellerA = await createSeller(context, 'Blue Kiln Studio')
  const sellerB = await createSeller(context, 'Rye Press')
  const sellerC = await createSeller(context, 'North Loom')

  const paidOut = await heldFulfillment(world, sellerA)
  await insertLedgerEntry(world, sellerA, 'released', 40_500, '2026-08-21T09:00:00.000Z', paidOut)
  await insertLedgerEntry(world, sellerA, 'paid_out', -40_500, '2026-08-24T23:59:59.999Z')

  const beforeRelease = await heldFulfillment(world, sellerB)
  await insertLedgerEntry(world, sellerB, 'refunded', -40_500, '2026-08-21T09:00:00.000Z', beforeRelease)

  const afterRelease = await heldFulfillment(world, sellerC)
  await insertLedgerEntry(world, sellerC, 'released', 40_500, '2026-08-21T09:00:00.000Z', afterRelease)
  await insertLedgerEntry(world, sellerC, 'refunded', -40_500, '2026-08-22T09:00:00.000Z', afterRelease)
  await insertLedgerEntry(world, sellerC, 'refunded', -5_000, '2026-08-23T09:00:00.000Z', null)

  return { sellerA, sellerB, sellerC, cutoff: '2026-08-21T23:59:59.999Z' }
}
