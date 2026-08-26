import { test } from 'node:test'
import assert from 'node:assert/strict'
import { activeListingRemoval } from './active-listing-removal.ts'
import { removedListing, removeListing } from './remove-listing.ts'
import { isOnStorefront } from '../../core/listings/listing-availability.ts'
import { createAdmin, createListing, createSeller, openCommerceWorld } from '../../test/commerce-world.ts'

test('a removed listing leaves the storefront and names its reason', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const sellerId = await createSeller(world.context)
  const adminId = await createAdmin(world.context)
  const listing = await createListing(world.context, sellerId)

  const removal = removedListing(
    await removeListing(world.context, {
      listingId: listing.id,
      adminId,
      kind: 'temporary',
      reason: 'The photograph is not the work.',
    }),
  )

  assert.equal(removal.kind, 'temporary')
  assert.equal(removal.adminId, adminId)
  assert.equal(removal.liftedAt, null)
  assert.equal(removal.createdAt, '2026-08-20T09:00:00.000Z')

  const active = await activeListingRemoval(world.context, listing.id)

  assert.equal(active?.reason, 'The photograph is not the work.')
  assert.equal(isOnStorefront(listing.status, active !== null), false)
})

test('a listing already removed is not removed a second time', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const sellerId = await createSeller(world.context)
  const adminId = await createAdmin(world.context)
  const listing = await createListing(world.context, sellerId)

  const first = removedListing(
    await removeListing(world.context, {
      listingId: listing.id,
      adminId,
      kind: 'temporary',
      reason: 'Retake the photograph.',
    }),
  )

  const result = await removeListing(world.context, {
    listingId: listing.id,
    adminId,
    kind: 'permanent',
    reason: 'Counterfeit.',
  })

  assert.deepEqual(result, {
    outcome: 'refused',
    reason: 'already_removed',
    data: { listing_id: listing.id, listing_removal_id: first.id },
  })

  const removals = await world.db.selectFrom('listingRemovals').selectAll().execute()

  assert.equal(removals.length, 1)
})
