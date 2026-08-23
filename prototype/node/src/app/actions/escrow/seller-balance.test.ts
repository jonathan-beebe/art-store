import { test } from 'node:test'
import assert from 'node:assert/strict'
import { sellerBalance } from './seller-balance.ts'
import { createSeller, openCommerceWorld } from '../../test/commerce-world.ts'

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

async function insertLedgerEntry(
  world: Awaited<ReturnType<typeof openCommerceWorld>>,
  sellerId: number,
  entryType: 'held' | 'released' | 'paid_out',
  amountCents: number,
  occurredAt: string,
): Promise<void> {
  await world.db
    .insertInto('ledgerEntries')
    .values({ sellerId, fulfillmentId: null, payoutId: null, entryType, amountCents, occurredAt })
    .execute()
}
