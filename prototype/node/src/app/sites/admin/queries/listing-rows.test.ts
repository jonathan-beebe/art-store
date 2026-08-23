import { test } from 'node:test'
import assert from 'node:assert/strict'
import { listingRows } from './listing-rows.ts'
import { removeListing } from '../../../actions/moderation/remove-listing.ts'
import { liftListingRemoval } from '../../../actions/moderation/lift-listing-removal.ts'
import {
  createAdmin,
  createListing,
  createSeller,
  openCommerceWorld,
} from '../../../test/commerce-world.ts'

test('lists every listing across sellers with seller name, price, quantity, and status', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const sellerId = await createSeller(world.context, 'Blue Kiln Studio')
  const listing = await createListing(world.context, sellerId, {
    title: 'Harbour at Dusk',
    priceCents: 45_000,
    quantity: 2,
  })

  const rows = await listingRows(world.context)

  assert.deepEqual(rows, [
    {
      id: listing.id,
      title: 'Harbour at Dusk',
      sellerId,
      sellerName: 'Blue Kiln Studio',
      status: 'for_sale',
      priceCents: 45_000,
      quantity: 2,
      isOnStorefront: true,
      activeRemoval: null,
    },
  ])
})

test('newest listings come first', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const sellerId = await createSeller(world.context)
  const first = await createListing(world.context, sellerId, { title: 'First' })
  const second = await createListing(world.context, sellerId, { title: 'Second' })

  const rows = await listingRows(world.context)

  assert.deepEqual(rows.map((row) => row.id), [second.id, first.id])
})

test('filters by status', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const sellerId = await createSeller(world.context)
  await createListing(world.context, sellerId, { title: 'Draft', status: 'draft' })
  const forSale = await createListing(world.context, sellerId, { title: 'For sale', status: 'for_sale' })

  const rows = await listingRows(world.context, { status: 'for_sale' })

  assert.deepEqual(rows.map((row) => row.id), [forSale.id])
})

test('filters by seller', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const first = await createSeller(world.context)
  const second = await createSeller(world.context)
  await createListing(world.context, first, { title: 'From first' })
  const wanted = await createListing(world.context, second, { title: 'From second' })

  const rows = await listingRows(world.context, { sellerId: second })

  assert.deepEqual(rows.map((row) => row.id), [wanted.id])
})

test('the default removed filter shows both removed and visible listings', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const sellerId = await createSeller(world.context)
  const adminId = await createAdmin(world.context)
  const visible = await createListing(world.context, sellerId, { title: 'Visible' })
  const removed = await createListing(world.context, sellerId, { title: 'Removed' })
  await removeListing(world.context, {
    listingId: removed.id,
    adminId,
    kind: 'temporary',
    reason: 'Reported artwork.',
  })

  const rows = await listingRows(world.context)

  assert.deepEqual(
    rows.map((row) => row.id).sort(),
    [visible.id, removed.id].sort(),
  )
})

test('removed=removed keeps only actively removed listings, with kind and reason', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const sellerId = await createSeller(world.context)
  const adminId = await createAdmin(world.context)
  await createListing(world.context, sellerId, { title: 'Visible' })
  const removed = await createListing(world.context, sellerId, { title: 'Removed' })
  await removeListing(world.context, {
    listingId: removed.id,
    adminId,
    kind: 'permanent',
    reason: 'Counterfeit.',
  })

  const rows = await listingRows(world.context, { removed: 'removed' })

  assert.deepEqual(rows, [
    {
      id: removed.id,
      title: 'Removed',
      sellerId,
      sellerName: 'Blue Kiln Studio',
      status: 'for_sale',
      priceCents: 45_000,
      quantity: 1,
      isOnStorefront: false,
      activeRemoval: { kind: 'permanent', reason: 'Counterfeit.' },
    },
  ])
})

test('removed=visible excludes actively removed listings', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const sellerId = await createSeller(world.context)
  const adminId = await createAdmin(world.context)
  const visible = await createListing(world.context, sellerId, { title: 'Visible' })
  const removed = await createListing(world.context, sellerId, { title: 'Removed' })
  await removeListing(world.context, {
    listingId: removed.id,
    adminId,
    kind: 'temporary',
    reason: 'Reported artwork.',
  })

  const rows = await listingRows(world.context, { removed: 'visible' })

  assert.deepEqual(rows.map((row) => row.id), [visible.id])
})

test('a lifted removal no longer counts as active', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const sellerId = await createSeller(world.context)
  const adminId = await createAdmin(world.context)
  const listing = await createListing(world.context, sellerId)
  await removeListing(world.context, {
    listingId: listing.id,
    adminId,
    kind: 'temporary',
    reason: 'Reported artwork.',
  })
  await liftListingRemoval(world.context, { listingId: listing.id })

  const rows = await listingRows(world.context, { removed: 'removed' })

  assert.deepEqual(rows, [])
})

test('a draft listing is never on the storefront, removed or not', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const sellerId = await createSeller(world.context)
  await createListing(world.context, sellerId, { status: 'draft' })

  const rows = await listingRows(world.context)

  assert.equal(rows[0]?.isOnStorefront, false)
})
