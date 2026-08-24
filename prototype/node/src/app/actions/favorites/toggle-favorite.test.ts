import { test } from 'node:test'
import assert from 'node:assert/strict'
import type { CustomerId, ListingId } from '../../core/ids/entity-ids.ts'
import { toggleFavorite } from './toggle-favorite.ts'
import type { AppDatabase } from '../../db/database.ts'
import { createCustomer, createListing, createSeller, openCommerceWorld } from '../../test/commerce-world.ts'

test('it saves a favorite and records a favorite event', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shopperId = await createCustomer(context)
  const sellerId = await createSeller(context)
  const art = await createListing(context, sellerId)

  const change = await toggleFavorite(context, { customerId: shopperId, listingId: art.id })

  assert.equal(change, 'added')
  assert.ok(await isFavorited(world.db, shopperId, art.id))
  assert.deepEqual(await eventTypes(world.db, art.id), ['favorite'])
})

test('toggling twice drops the favorite and records an unfavorite event', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shopperId = await createCustomer(context)
  const sellerId = await createSeller(context)
  const art = await createListing(context, sellerId)
  await toggleFavorite(context, { customerId: shopperId, listingId: art.id })

  const change = await toggleFavorite(context, { customerId: shopperId, listingId: art.id })

  assert.equal(change, 'removed')
  assert.equal(await isFavorited(world.db, shopperId, art.id), false)
  assert.deepEqual(await eventTypes(world.db, art.id), ['favorite', 'unfavorite'])
})

test('the event is recorded against the visitor who saved it', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shopperId = await createCustomer(context)
  const sellerId = await createSeller(context)
  const art = await createListing(context, sellerId)

  await toggleFavorite(context, { customerId: shopperId, listingId: art.id })

  const event = await world.db
    .selectFrom('listingEvents')
    .select('customerId')
    .where('listingId', '=', art.id)
    .executeTakeFirstOrThrow()

  assert.equal(event.customerId, shopperId)
})

test("one visitor saving leaves another visitor's favorites alone", async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shopperId = await createCustomer(context)
  const otherId = await createCustomer(context)
  const sellerId = await createSeller(context)
  const art = await createListing(context, sellerId)
  await toggleFavorite(context, { customerId: shopperId, listingId: art.id })

  const change = await toggleFavorite(context, { customerId: otherId, listingId: art.id })

  assert.equal(change, 'added')
  assert.ok(await isFavorited(world.db, shopperId, art.id))
})

async function isFavorited(db: AppDatabase, customerId: CustomerId, listingId: ListingId): Promise<boolean> {
  const row = await db
    .selectFrom('favorites')
    .select('id')
    .where('customerId', '=', customerId)
    .where('listingId', '=', listingId)
    .executeTakeFirst()

  return row !== undefined
}

async function eventTypes(db: AppDatabase, listingId: ListingId): Promise<string[]> {
  const rows = await db
    .selectFrom('listingEvents')
    .select('eventType')
    .where('listingId', '=', listingId)
    .orderBy('occurredAt')
    .orderBy('id')
    .execute()

  return rows.map((row) => row.eventType)
}
