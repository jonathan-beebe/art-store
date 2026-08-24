import { test } from 'node:test'
import assert from 'node:assert/strict'
import { newId } from '../../../ids.ts'
import { customerRows } from './customer-rows.ts'
import { blockCustomer } from '../../../actions/moderation/block-customer.ts'
import { toTimestamp } from '../../../db/timestamp.ts'
import {
  cartHolding,
  createAdmin,
  createCustomer,
  createListing,
  createSeller,
  openCommerceWorld,
  placedOrder,
} from '../../../test/commerce-world.ts'

test('an empty platform lists no customers', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  assert.deepEqual(await customerRows(world.context), [])
})

test('counts and standing fold per customer', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const sellerId = await createSeller(world.context)
  const listing = await createListing(world.context, sellerId)
  const listingTwo = await createListing(world.context, sellerId)

  const verified = await createCustomer(world.context, { isVerified: true })
  const anonymous = await createCustomer(world.context, { isVerified: false })
  const blocked = await createCustomer(world.context, { isVerified: true })
  const adminId = await createAdmin(world.context)

  await placedOrder(world.context, verified, [listing.id])
  await cartHolding(world.context, verified, [listingTwo.id])
  await world.db
    .insertInto('favorites')
    .values({ id: newId('fav', new Date()), customerId: verified, listingId: listing.id, createdAt: toTimestamp(new Date()) })
    .execute()

  await blockCustomer(world.context, { customerId: blocked, adminId, reason: 'Chargeback fraud.' })

  const rows = await customerRows(world.context)
  const byId = new Map(rows.map((row) => [row.id, row]))

  const verifiedRow = byId.get(verified)
  assert.equal(verifiedRow?.orderCount, 1)
  assert.equal(verifiedRow?.favoriteCount, 1)
  assert.equal(verifiedRow?.cartLineCount, 1)
  assert.equal(verifiedRow?.isBlocked, false)

  const anonymousRow = byId.get(anonymous)
  assert.equal(anonymousRow?.email, null)
  assert.equal(anonymousRow?.orderCount, 0)

  const blockedRow = byId.get(blocked)
  assert.equal(blockedRow?.isBlocked, true)
})

test('the standing filter narrows to verified, anonymous, or blocked', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const verified = await createCustomer(world.context, { isVerified: true })
  const anonymous = await createCustomer(world.context, { isVerified: false })
  const blocked = await createCustomer(world.context, { isVerified: true })
  const adminId = await createAdmin(world.context)
  await blockCustomer(world.context, { customerId: blocked, adminId, reason: 'Chargeback fraud.' })

  const verifiedRows = await customerRows(world.context, 'verified')
  assert.deepEqual(verifiedRows.map((row) => row.id).sort(), [verified, blocked].sort())

  const anonymousRows = await customerRows(world.context, 'anonymous')
  assert.deepEqual(anonymousRows.map((row) => row.id), [anonymous])

  const blockedRows = await customerRows(world.context, 'blocked')
  assert.deepEqual(blockedRows.map((row) => row.id), [blocked])

  const allRows = await customerRows(world.context, 'all')
  assert.equal(allRows.length, 3)
})
