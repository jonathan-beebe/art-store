import { test } from 'node:test'
import assert from 'node:assert/strict'
import { activeListingRemoval } from './active-listing-removal.ts'
import { liftListingRemoval } from './lift-listing-removal.ts'
import { removeListing } from './remove-listing.ts'
import { isOnStorefront } from '../../core/listings/listing-availability.ts'
import { TransitionError } from '../../core/transition-error.ts'
import { createAdmin, createListing, createSeller, openCommerceWorld } from '../../test/commerce-world.ts'

test('lifting a temporary removal puts the listing back on the storefront', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const sellerId = await createSeller(world.context)
  const adminId = await createAdmin(world.context)
  const listing = await createListing(world.context, sellerId)

  await removeListing(world.context, {
    listingId: listing.id,
    adminId,
    kind: 'temporary',
    reason: 'Retake the photograph.',
  })

  world.travelTo(new Date('2026-08-21T09:00:00.000Z'))
  const lifted = await liftListingRemoval(world.context, { listingId: listing.id })

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

  await removeListing(world.context, {
    listingId: listing.id,
    adminId,
    kind: 'permanent',
    reason: 'Counterfeit.',
  })

  await assert.rejects(
    () => liftListingRemoval(world.context, { listingId: listing.id }),
    TransitionError,
  )
  assert.notEqual(await activeListingRemoval(world.context, listing.id), null)
})

test('a listing nobody removed cannot be lifted', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const sellerId = await createSeller(world.context)
  const listing = await createListing(world.context, sellerId)

  await assert.rejects(
    () => liftListingRemoval(world.context, { listingId: listing.id }),
    TransitionError,
  )
})
