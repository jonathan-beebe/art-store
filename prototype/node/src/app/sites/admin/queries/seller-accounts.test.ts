import { test } from 'node:test'
import assert from 'node:assert/strict'
import { sellerAccounts, sellerOptions } from './seller-accounts.ts'
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

test('a seller with no activity shows a zeroed, reconciled row', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const sellerId = await createSeller(world.context, 'Blue Kiln Studio')

  assert.deepEqual(await sellerAccounts(world.context), [
    {
      sellerId,
      sellerName: 'Blue Kiln Studio',
      heldCents: 0,
      availableCents: 0,
      paidOutCents: 0,
      payoutTotalCents: 0,
      reconciles: true,
      lifetimeSubtotalCents: 0,
      lifetimeFeeCents: 0,
      lifetimeNetCents: 0,
    },
  ])
})

test('a paid, undelivered order holds the net and counts as a lifetime sale', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const sellerId = await createSeller(world.context, 'Blue Kiln Studio')
  const customerId = await createCustomer(world.context)
  const listing = await createListing(world.context, sellerId, { priceCents: 45_000 })

  await paidOrder(world.context, customerId, [listing.id])

  const [account] = await sellerAccounts(world.context)
  assert.equal(account?.heldCents, 40_500)
  assert.equal(account?.availableCents, 0)
  assert.equal(account?.paidOutCents, 0)
  assert.equal(account?.reconciles, true)
  assert.equal(account?.lifetimeSubtotalCents, 45_000)
  assert.equal(account?.lifetimeFeeCents, 4_500)
  assert.equal(account?.lifetimeNetCents, 40_500)
})

test('delivery and a payout run move the balance and reconcile against payouts', async (t) => {
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

  const [account] = await sellerAccounts(world.context)
  assert.equal(account?.heldCents, 0)
  assert.equal(account?.availableCents, 0)
  assert.equal(account?.paidOutCents, 40_500)
  assert.equal(account?.payoutTotalCents, 40_500)
  assert.equal(account?.reconciles, true)
})

test('two sellers each get their own row, read once for both', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const customerId = await createCustomer(world.context)
  const first = await createSeller(world.context, 'Blue Kiln Studio')
  const second = await createSeller(world.context, 'Rye Press')
  const firstListing = await createListing(world.context, first, { priceCents: 45_000 })
  const secondListing = await createListing(world.context, second, { priceCents: 20_000 })

  await paidOrder(world.context, customerId, [firstListing.id, secondListing.id])

  const accounts = await sellerAccounts(world.context)
  const bySeller = new Map(accounts.map((account) => [account.sellerId, account]))

  assert.equal(bySeller.get(first)?.heldCents, 40_500)
  assert.equal(bySeller.get(second)?.heldCents, 18_000)
})

test('a payout total that does not match the ledger does not reconcile', async (t) => {
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

  // A payout row not backed by a matching ledger entry is the shape of a bug this page must surface.
  await world.db
    .insertInto('payouts')
    .values({
      sellerId,
      periodStart: '2026-08-24',
      periodEnd: '2026-08-30',
      amountCents: 1_000,
      paidAt: '2026-08-31T00:00:00.000Z',
    })
    .execute()

  const [account] = await sellerAccounts(world.context)
  assert.equal(account?.reconciles, false)
})

test('sellerOptions names a seller by shop name and falls back to email', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const namedId = await createSeller(world.context, 'Blue Kiln Studio')
  const unnamedId = await world.db
    .insertInto('sellers')
    .values({
      email: 'unnamed@example.test',
      name: null,
      shopName: null,
      emailVerifiedAt: null,
      createdAt: '2026-08-20T09:00:00.000Z',
    })
    .returning('id')
    .executeTakeFirstOrThrow()
    .then((row) => row.id)

  const options = await sellerOptions(world.context)
  const byId = new Map(options.map((option) => [option.id, option.name]))

  assert.equal(byId.get(namedId), 'Blue Kiln Studio')
  assert.equal(byId.get(unnamedId), 'unnamed@example.test')
})
