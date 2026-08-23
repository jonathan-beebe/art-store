import { test } from 'node:test'
import assert from 'node:assert/strict'
import { sellerRows } from './seller-rows.ts'
import { removeListing } from '../../../actions/moderation/remove-listing.ts'
import {
  createAdmin,
  createCustomer,
  createListing,
  createSeller,
  openCommerceWorld,
  paidOrder,
} from '../../../test/commerce-world.ts'

test('an empty platform lists no sellers', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  assert.deepEqual(await sellerRows(world.context), [])
})

test('a seller with nothing yet reads zero everywhere', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const sellerId = await createSeller(world.context, 'Blue Kiln Studio')

  const rows = await sellerRows(world.context)

  assert.equal(rows.length, 1)
  assert.deepEqual(rows[0], {
    id: sellerId,
    email: rows[0]!.email,
    shopName: 'Blue Kiln Studio',
    createdAt: rows[0]!.createdAt,
    listingCount: 0,
    fulfillmentCount: 0,
    removedListingCount: 0,
    heldCents: 0,
    availableCents: 0,
    paidOutCents: 0,
  })
})

test('listings, fulfillments, removals, and balance fold per seller', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const sellerA = await createSeller(world.context, 'Blue Kiln Studio')
  const sellerB = await createSeller(world.context, 'Red Clay Works')
  const customerId = await createCustomer(world.context)
  const adminId = await createAdmin(world.context)

  const listingA1 = await createListing(world.context, sellerA, { priceCents: 10_000 })
  await createListing(world.context, sellerA, { priceCents: 20_000 })
  const listingB1 = await createListing(world.context, sellerB, { priceCents: 30_000 })

  await paidOrder(world.context, customerId, [listingA1.id])
  await removeListing(world.context, {
    listingId: listingB1.id,
    adminId,
    kind: 'temporary',
    reason: 'Reported as counterfeit.',
  })

  const rows = await sellerRows(world.context)
  const bySellerId = new Map(rows.map((row) => [row.id, row]))

  const rowA = bySellerId.get(sellerA)
  assert.ok(rowA)
  assert.equal(rowA.listingCount, 2)
  assert.equal(rowA.fulfillmentCount, 1)
  assert.equal(rowA.removedListingCount, 0)
  assert.equal(rowA.heldCents, 9_000)
  assert.equal(rowA.availableCents, 0)
  assert.equal(rowA.paidOutCents, 0)

  const rowB = bySellerId.get(sellerB)
  assert.ok(rowB)
  assert.equal(rowB.listingCount, 1)
  assert.equal(rowB.fulfillmentCount, 0)
  assert.equal(rowB.removedListingCount, 1)
  assert.equal(rowB.heldCents, 0)
})
