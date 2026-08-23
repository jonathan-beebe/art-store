import { test } from 'node:test'
import assert from 'node:assert/strict'
import { changeListingStatus } from './change-listing-status.ts'
import { TransitionError } from '../../core/transition-error.ts'
import type { AppDatabase } from '../../db/database.ts'
import { toTimestamp } from '../../db/timestamp.ts'
import { createAdmin, createListing, createSeller, openCommerceWorld } from '../../test/commerce-world.ts'

test('a draft goes on sale', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const sellerId = await createSeller(context)
  const art = await createListing(context, sellerId, { status: 'draft' })

  const updated = await changeListingStatus(context, { listingId: art.id, status: 'for_sale' })

  assert.equal(updated.status, 'for_sale')
})

test('a listing on sale is archived', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const sellerId = await createSeller(context)
  const art = await createListing(context, sellerId, { status: 'for_sale' })

  const updated = await changeListingStatus(context, { listingId: art.id, status: 'archived' })

  assert.equal(updated.status, 'archived')
})

test('a move the lifecycle refuses throws and leaves the row where it was', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const sellerId = await createSeller(context)
  const art = await createListing(context, sellerId, { status: 'draft' })

  await assert.rejects(
    () => changeListingStatus(context, { listingId: art.id, status: 'sold' }),
    TransitionError,
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
      listingId: art.id,
      adminId,
      kind: 'permanent',
      reason: 'Reported as counterfeit.',
      createdAt: toTimestamp(context.clock.now()),
      liftedAt: null,
    })
    .execute()

  await assert.rejects(
    () => changeListingStatus(context, { listingId: art.id, status: 'for_sale' }),
    (error: unknown) =>
      error instanceof TransitionError &&
      error.message === 'This listing was removed by an admin and cannot be put back on sale.',
  )

  assert.equal(await readStatus(world.db, art.id), 'sold')
})

async function readStatus(db: AppDatabase, listingId: number): Promise<string> {
  const row = await db
    .selectFrom('listings')
    .select('status')
    .where('id', '=', listingId)
    .executeTakeFirstOrThrow()

  return row.status
}
