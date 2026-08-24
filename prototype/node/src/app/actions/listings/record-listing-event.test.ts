import { test } from 'node:test'
import assert from 'node:assert/strict'
import type { ListingId } from '../../core/ids/entity-ids.ts'
import { recordListingEvent } from './record-listing-event.ts'
import type { ListingEventType } from '../../core/listings/listing-event-type.ts'
import type { AppDatabase } from '../../db/database.ts'
import { createCustomer, createListing, createSeller, openCommerceWorld } from '../../test/commerce-world.ts'

test('it records what happened and when', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const sellerId = await createSeller(context)
  const art = await createListing(context, sellerId)
  const customerId = await createCustomer(context)

  world.travelTo(new Date('2026-08-20T08:00:00.000Z'))
  const event = await recordListingEvent(context, {
    listingId: art.id,
    customerId,
    eventType: 'view',
  })

  assert.equal(event?.listingId, art.id)
  assert.equal(event?.eventType, 'view')
  assert.equal(event?.occurredAt, '2026-08-20T08:00:00.000Z')
})

test('an anonymous visitor leaves an event with no customer', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const sellerId = await createSeller(context)
  const art = await createListing(context, sellerId)

  const event = await recordListingEvent(context, {
    listingId: art.id,
    customerId: null,
    eventType: 'view',
  })

  assert.equal(event?.customerId, null)
})

test('a second view from the same customer in the same hour writes nothing and returns null', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const sellerId = await createSeller(context)
  const art = await createListing(context, sellerId)
  const customerId = await createCustomer(context)

  world.travelTo(new Date('2026-08-20T08:00:00.000Z'))
  await recordListingEvent(context, { listingId: art.id, customerId, eventType: 'view' })

  world.travelTo(new Date('2026-08-20T08:45:00.000Z'))
  const second = await recordListingEvent(context, { listingId: art.id, customerId, eventType: 'view' })

  assert.equal(second, null)
  assert.equal(await countEvents(world.db, art.id, 'view'), 1)
})

test('a view in the next hour writes a second row', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const sellerId = await createSeller(context)
  const art = await createListing(context, sellerId)
  const customerId = await createCustomer(context)

  world.travelTo(new Date('2026-08-20T08:00:00.000Z'))
  await recordListingEvent(context, { listingId: art.id, customerId, eventType: 'view' })

  world.travelTo(new Date('2026-08-20T09:00:00.000Z'))
  const second = await recordListingEvent(context, { listingId: art.id, customerId, eventType: 'view' })

  assert.notEqual(second, null)
  assert.equal(await countEvents(world.db, art.id, 'view'), 2)
})

test('two different customers viewing in the same hour each get a row', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const sellerId = await createSeller(context)
  const art = await createListing(context, sellerId)
  const first = await createCustomer(context)
  const second = await createCustomer(context)

  world.travelTo(new Date('2026-08-20T08:00:00.000Z'))
  await recordListingEvent(context, { listingId: art.id, customerId: first, eventType: 'view' })
  await recordListingEvent(context, { listingId: art.id, customerId: second, eventType: 'view' })

  assert.equal(await countEvents(world.db, art.id, 'view'), 2)
})

test('a second anonymous view in the same hour is also deduped', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const sellerId = await createSeller(context)
  const art = await createListing(context, sellerId)

  world.travelTo(new Date('2026-08-20T08:00:00.000Z'))
  await recordListingEvent(context, { listingId: art.id, customerId: null, eventType: 'view' })
  const second = await recordListingEvent(context, { listingId: art.id, customerId: null, eventType: 'view' })

  assert.equal(second, null)
  assert.equal(await countEvents(world.db, art.id, 'view'), 1)
})

test('a non-view event type is never deduped', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const sellerId = await createSeller(context)
  const art = await createListing(context, sellerId)
  const customerId = await createCustomer(context)

  world.travelTo(new Date('2026-08-20T08:00:00.000Z'))
  await recordListingEvent(context, { listingId: art.id, customerId, eventType: 'cart_add' })
  await recordListingEvent(context, { listingId: art.id, customerId, eventType: 'cart_add' })

  assert.equal(await countEvents(world.db, art.id, 'cart_add'), 2)
})

async function countEvents(db: AppDatabase, listingId: ListingId, eventType: ListingEventType): Promise<number> {
  const rows = await db
    .selectFrom('listingEvents')
    .select('id')
    .where('listingId', '=', listingId)
    .where('eventType', '=', eventType)
    .execute()

  return rows.length
}
