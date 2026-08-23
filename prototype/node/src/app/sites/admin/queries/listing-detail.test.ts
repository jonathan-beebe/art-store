import { test } from 'node:test'
import assert from 'node:assert/strict'
import { listingDetail, type ListingDetail, type ListingDetailRemoval } from './listing-detail.ts'
import { removeListing } from '../../../actions/moderation/remove-listing.ts'
import { liftListingRemoval } from '../../../actions/moderation/lift-listing-removal.ts'
import {
  createAdmin,
  createListing,
  createSeller,
  openCommerceWorld,
} from '../../../test/commerce-world.ts'

function requireDetail(detail: ListingDetail | null): ListingDetail {
  if (detail === null) throw new Error('expected a listing')
  return detail
}

function requireActiveRemoval(detail: ListingDetail): ListingDetailRemoval {
  if (detail.activeRemoval === null) throw new Error('expected an active removal')
  return detail.activeRemoval
}

function nth<T>(items: readonly T[], index: number): T {
  const item = items[index]
  if (item === undefined) throw new Error(`expected an element at index ${index}`)
  return item
}

test('a listing with no removal history', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const sellerId = await createSeller(world.context, 'Blue Kiln Studio')
  const listing = await createListing(world.context, sellerId, { title: 'Harbour at Dusk' })

  const detail = await listingDetail(world.context, listing.id)

  assert.deepEqual(detail, {
    listing,
    seller: { id: sellerId, name: 'Blue Kiln Studio' },
    isOnStorefront: true,
    activeRemoval: null,
    removals: [],
  })
})

test('nothing answers for an id that names no listing', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  assert.equal(await listingDetail(world.context, 999), null)
})

test('an actively removed listing carries the removal and says it can be lifted', async (t) => {
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

  const detail = requireDetail(await listingDetail(world.context, listing.id))
  const active = requireActiveRemoval(detail)

  assert.equal(detail.isOnStorefront, false)
  assert.equal(active.kind, 'temporary')
  assert.equal(active.reason, 'Reported artwork.')
  assert.equal(active.canLift, true)
  assert.equal(detail.removals.length, 1)
})

test('a permanent removal cannot be lifted', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const sellerId = await createSeller(world.context)
  const adminId = await createAdmin(world.context)
  const listing = await createListing(world.context, sellerId)
  await removeListing(world.context, {
    listingId: listing.id,
    adminId,
    kind: 'permanent',
    reason: 'Counterfeit.',
  })

  const detail = requireDetail(await listingDetail(world.context, listing.id))
  const active = requireActiveRemoval(detail)

  assert.equal(active.canLift, false)
})

test('the full removal history is kept in order, lifted removals included', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const sellerId = await createSeller(world.context)
  const adminId = await createAdmin(world.context)
  const listing = await createListing(world.context, sellerId)
  await removeListing(world.context, {
    listingId: listing.id,
    adminId,
    kind: 'temporary',
    reason: 'First removal.',
  })
  await liftListingRemoval(world.context, { listingId: listing.id })
  await removeListing(world.context, {
    listingId: listing.id,
    adminId,
    kind: 'permanent',
    reason: 'Second removal.',
  })

  const detail = requireDetail(await listingDetail(world.context, listing.id))
  const active = requireActiveRemoval(detail)
  const first = nth(detail.removals, 0)
  const second = nth(detail.removals, 1)

  assert.equal(detail.removals.length, 2)
  assert.equal(first.reason, 'First removal.')
  assert.notEqual(first.liftedAt, null)
  assert.equal(second.reason, 'Second removal.')
  assert.equal(second.liftedAt, null)
  assert.equal(active.reason, 'Second removal.')
})
