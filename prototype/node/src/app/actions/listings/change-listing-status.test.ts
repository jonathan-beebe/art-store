import { test } from 'node:test'
import assert from 'node:assert/strict'
import type { ListingId } from '../../core/ids/entity-ids.ts'
import { BrokenContractError } from '../../core/defect.ts'
import { refused } from '../../core/refusal.ts'
import { newId } from '../../ids.ts'
import { changeListingStatus, changedListing, listingStatusRefusalCopy } from './change-listing-status.ts'
import type { AppDatabase } from '../../db/database.ts'
import { toTimestamp } from '../../db/timestamp.ts'
import { createAdmin, createListing, createSeller, openCommerceWorld } from '../../test/commerce-world.ts'

test('a draft goes on sale', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const sellerId = await createSeller(context)
  const art = await createListing(context, sellerId, { status: 'draft' })

  const result = await changeListingStatus(context, { listingId: art.id, status: 'for_sale' })

  assert.equal(result.outcome, 'changed')
  assert.equal(changedListing(result).status, 'for_sale')
})

test('a listing on sale is archived', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const sellerId = await createSeller(context)
  const art = await createListing(context, sellerId, { status: 'for_sale' })

  const result = await changeListingStatus(context, { listingId: art.id, status: 'archived' })

  assert.equal(result.outcome, 'changed')
  assert.equal(changedListing(result).status, 'archived')
})

test('a move the lifecycle refuses is a refusal, and leaves the row where it was', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const sellerId = await createSeller(context)
  const art = await createListing(context, sellerId, { status: 'draft' })

  const result = await changeListingStatus(context, { listingId: art.id, status: 'sold' })

  assert.deepEqual(
    result,
    refused('illegal_transition', { listing_id: art.id, status_from: 'draft', status_to: 'sold' }),
  )
  assert.equal(await readStatus(world.db, art.id), 'draft')
})

test('a removed listing refuses to go back on sale, even through a transition the lifecycle table allows', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const sellerId = await createSeller(context)
  const adminId = await createAdmin(context)
  const art = await createListing(context, sellerId, { status: 'sold' })
  await world.db
    .insertInto('listingRemovals')
    .values({
      id: newId('rmv', new Date()),
      listingId: art.id,
      adminId,
      kind: 'permanent',
      reason: 'Reported as counterfeit.',
      createdAt: toTimestamp(context.clock.now()),
      liftedAt: null,
    })
    .execute()

  const result = await changeListingStatus(context, { listingId: art.id, status: 'for_sale' })

  assert.deepEqual(
    result,
    refused('listing_removed', { listing_id: art.id, status_from: 'sold', status_to: 'for_sale' }),
  )
  assert.equal(await readStatus(world.db, art.id), 'sold')
})

test('changedListing unwraps a changed result to its listing', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const sellerId = await createSeller(context)
  const art = await createListing(context, sellerId, { status: 'draft' })

  const result = await changeListingStatus(context, { listingId: art.id, status: 'for_sale' })

  assert.equal(changedListing(result).id, art.id)
})

test('changedListing throws for a refusal, carrying its reason', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const sellerId = await createSeller(context)
  const art = await createListing(context, sellerId, { status: 'draft' })

  const result = await changeListingStatus(context, { listingId: art.id, status: 'sold' })

  assert.throws(
    () => changedListing(result),
    (error: unknown) => error instanceof BrokenContractError && error.reason === 'illegal_transition',
  )
})

test('listingStatusRefusalCopy words a removal regardless of the transition data', () => {
  const refusal = refused('listing_removed', { listing_id: 'lst_1', status_from: 'sold', status_to: 'for_sale' })

  assert.equal(
    listingStatusRefusalCopy(refusal),
    'This listing was removed by an admin and cannot be put back on sale.',
  )
})

test('listingStatusRefusalCopy words an illegal transition from the refusal data', () => {
  const refusal = refused('illegal_transition', {
    listing_id: 'lst_1',
    status_from: 'draft',
    status_to: 'sold',
  } as const)

  assert.equal(listingStatusRefusalCopy(refusal), 'A listing cannot move from draft to sold.')
})

test('listingStatusRefusalCopy renders the refusal data, not a status a route read before the race', () => {
  // The action's refusal carries the status as of the write; the route's earlier
  // row read is stale by the time a concurrent move lands first.
  const routeReadBeforeTheRace = 'draft'
  const refusal = refused('illegal_transition', { status_from: 'archived', status_to: 'for_sale' } as const)

  const sentence = listingStatusRefusalCopy(refusal)

  assert.match(sentence, /archived/)
  assert.doesNotMatch(sentence, new RegExp(routeReadBeforeTheRace))
})

async function readStatus(db: AppDatabase, listingId: ListingId): Promise<string> {
  const row = await db
    .selectFrom('listings')
    .select('status')
    .where('id', '=', listingId)
    .executeTakeFirstOrThrow()

  return row.status
}
