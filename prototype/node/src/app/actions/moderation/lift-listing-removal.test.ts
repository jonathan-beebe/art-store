import { test } from 'node:test'
import assert from 'node:assert/strict'
import { activeListingRemoval } from './active-listing-removal.ts'
import { liftListingRemoval } from './lift-listing-removal.ts'
import { removeListing } from './remove-listing.ts'
import { isOnStorefront } from '../../core/listings/listing-availability.ts'
import { mustSucceed } from '../../core/refusal.ts'
import { createAdmin, createListing, createSeller, openCommerceWorld } from '../../test/commerce-world.ts'

test('lifting a temporary removal puts the listing back on the storefront', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const sellerId = await createSeller(world.context)
  const adminId = await createAdmin(world.context)
  const listing = await createListing(world.context, sellerId)

  mustSucceed(
    await removeListing(world.context, {
      listingId: listing.id,
      adminId,
      kind: 'temporary',
      reason: 'Retake the photograph.',
    }),
  )

  world.travelTo(new Date('2026-08-21T09:00:00.000Z'))
  const lifted = mustSucceed(await liftListingRemoval(world.context, { listingId: listing.id })).removal

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

  const removal = mustSucceed(
    await removeListing(world.context, {
      listingId: listing.id,
      adminId,
      kind: 'permanent',
      reason: 'Counterfeit.',
    }),
  ).removal

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
