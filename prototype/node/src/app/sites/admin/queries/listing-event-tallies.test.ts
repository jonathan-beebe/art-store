import { test } from 'node:test'
import assert from 'node:assert/strict'
import { listingEventTallies } from './listing-event-tallies.ts'
import { recordListingEvent } from '../../../actions/listings/record-listing-event.ts'
import { toggleFavorite } from '../../../actions/favorites/toggle-favorite.ts'
import {
  createCustomer,
  createListing,
  createSeller,
  openCommerceWorld,
} from '../../../test/commerce-world.ts'

test('an untouched platform still names every event type', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  assert.deepEqual(await listingEventTallies(world.context), [
    { key: 'view', count: 0 },
    { key: 'favorite', count: 0 },
    { key: 'unfavorite', count: 0 },
    { key: 'cart_add', count: 0 },
  ])
})

test('events are counted by type', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const sellerId = await createSeller(world.context)
  const listing = await createListing(world.context, sellerId)
  const first = await createCustomer(world.context)
  const second = await createCustomer(world.context)

  await recordListingEvent(world.context, {
    listingId: listing.id,
    customerId: first,
    eventType: 'view',
  })
  await recordListingEvent(world.context, {
    listingId: listing.id,
    customerId: second,
    eventType: 'view',
  })
  await toggleFavorite(world.context, { customerId: first, listingId: listing.id })
  await toggleFavorite(world.context, { customerId: first, listingId: listing.id })

  assert.deepEqual(await listingEventTallies(world.context), [
    { key: 'view', count: 2 },
    { key: 'favorite', count: 1 },
    { key: 'unfavorite', count: 1 },
    { key: 'cart_add', count: 0 },
  ])
})
