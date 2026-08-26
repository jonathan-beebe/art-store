import { test } from 'node:test'
import assert from 'node:assert/strict'
import { activeListingRemoval } from './active-listing-removal.ts'
import { liftedListingRemoval, liftListingRemoval } from './lift-listing-removal.ts'
import { removedListing, removeListing } from './remove-listing.ts'
import { isOnStorefront } from '../../core/listings/listing-availability.ts'
import { createAdmin, createListing, createSeller, openCommerceWorld } from '../../test/commerce-world.ts'

test('lifting a temporary removal puts the listing back on the storefront', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const sellerId = await createSeller(world.context)
  const adminId = await createAdmin(world.context)
  const listing = await createListing(world.context, sellerId)

  removedListing(
    await removeListing(world.context, {
      listingId: listing.id,
      adminId,
      kind: 'temporary',
      reason: 'Retake the photograph.',
    }),
  )

  world.travelTo(new Date('2026-08-21T09:00:00.000Z'))
  const lifted = liftedListingRemoval(await liftListingRemoval(world.context, { listingId: listing.id }))

  assert.equal(lifted.liftedAt, '2026-08-21T09:00:00.000Z')
  assert.equal(await activeListingRemoval(world.context, listing.id), null)
  assert.equal(isOnStorefront(listing.status, false), true)
})

test('a permanent removal is refused, and the listing stays off', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const sellerId = await createSeller(world.context)
  const adminId = await createAdmin(world.context)
  const listing = await createListing(world.context, sellerId)

  const removal = removedListing(
    await removeListing(world.context, {
      listingId: listing.id,
      adminId,
      kind: 'permanent',
      reason: 'Counterfeit.',
    }),
  )

  const result = await liftListingRemoval(world.context, { listingId: listing.id })

  assert.deepEqual(result, {
    outcome: 'refused',
    reason: 'permanent_removal',
    data: { listing_id: listing.id, listing_removal_id: removal.id },
  })
  assert.notEqual(await activeListingRemoval(world.context, listing.id), null)
})

test('a listing nobody removed cannot be lifted', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const sellerId = await createSeller(world.context)
  const listing = await createListing(world.context, sellerId)

  const result = await liftListingRemoval(world.context, { listingId: listing.id })

  assert.deepEqual(result, {
    outcome: 'refused',
    reason: 'not_removed',
    data: { listing_id: listing.id },
  })
})
