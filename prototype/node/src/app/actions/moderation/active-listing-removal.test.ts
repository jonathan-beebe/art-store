import { test } from 'node:test'
import assert from 'node:assert/strict'
import { activeListingRemoval } from './active-listing-removal.ts'
import { toTimestamp } from '../../db/timestamp.ts'
import { createAdmin, createListing, createSeller, openCommerceWorld } from '../../test/commerce-world.ts'

test('a listing with no removals has none', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const sellerId = await createSeller(context)
  const art = await createListing(context, sellerId)

  const removal = await activeListingRemoval(context, art.id)

  assert.equal(removal, null)
})

test('an unlifted removal comes back with its kind and reason and its dates as Dates', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context, db } = world

  const sellerId = await createSeller(context)
  const art = await createListing(context, sellerId)
  const adminId = await createAdmin(context)
  const createdAt = new Date('2026-08-20T09:00:00.000Z')

  await db
    .insertInto('listingRemovals')
    .values({
      listingId: art.id,
      adminId,
      kind: 'temporary',
      reason: 'Reported as counterfeit',
      createdAt: toTimestamp(createdAt),
      liftedAt: null,
    })
    .execute()

  const removal = await activeListingRemoval(context, art.id)

  assert.equal(removal?.kind, 'temporary')
  assert.equal(removal?.reason, 'Reported as counterfeit')
  assert.deepEqual(removal?.createdAt, createdAt)
  assert.equal(removal?.liftedAt, null)
})

test('a lifted removal is not active', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context, db } = world

  const sellerId = await createSeller(context)
  const art = await createListing(context, sellerId)
  const adminId = await createAdmin(context)

  await db
    .insertInto('listingRemovals')
    .values({
      listingId: art.id,
      adminId,
      kind: 'temporary',
      reason: 'Reported as counterfeit',
      createdAt: toTimestamp(new Date('2026-08-20T09:00:00.000Z')),
      liftedAt: toTimestamp(new Date('2026-08-21T09:00:00.000Z')),
    })
    .execute()

  const removal = await activeListingRemoval(context, art.id)

  assert.equal(removal, null)
})

test('the unlifted one wins when a listing has both', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context, db } = world

  const sellerId = await createSeller(context)
  const art = await createListing(context, sellerId)
  const adminId = await createAdmin(context)

  await db
    .insertInto('listingRemovals')
    .values({
      listingId: art.id,
      adminId,
      kind: 'temporary',
      reason: 'First, lifted',
      createdAt: toTimestamp(new Date('2026-08-20T09:00:00.000Z')),
      liftedAt: toTimestamp(new Date('2026-08-20T10:00:00.000Z')),
    })
    .execute()
  await db
    .insertInto('listingRemovals')
    .values({
      listingId: art.id,
      adminId,
      kind: 'permanent',
      reason: 'Second, still active',
      createdAt: toTimestamp(new Date('2026-08-21T09:00:00.000Z')),
      liftedAt: null,
    })
    .execute()

  const removal = await activeListingRemoval(context, art.id)

  assert.equal(removal?.reason, 'Second, still active')
})

