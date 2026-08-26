import { test } from 'node:test'
import assert from 'node:assert/strict'
import { newId } from '../../../ids.ts'
import { countCustomerRows, customerRows } from './customer-rows.ts'
import { blockCustomer } from '../../../actions/moderation/block-customer.ts'
import type { CustomerId } from '../../../core/ids/entity-ids.ts'
import {
  isAnonymousCustomer,
  isVerifiedCustomer,
} from '../../../core/customers/customer-verification.ts'
import { mustSucceed } from '../../../core/refusal.ts'
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

const FULL_PAGE = { offset: 0, limit: 100 }

test('an empty platform lists no customers', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  assert.deepEqual(await customerRows(world.context, 'all', FULL_PAGE), [])
  assert.equal(await countCustomerRows(world.context), 0)
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

  mustSucceed(await blockCustomer(world.context, { customerId: blocked, adminId, reason: 'Chargeback fraud.' }))

  const rows = await customerRows(world.context, 'all', FULL_PAGE)
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
  mustSucceed(await blockCustomer(world.context, { customerId: blocked, adminId, reason: 'Chargeback fraud.' }))

  const verifiedRows = await customerRows(world.context, 'verified', FULL_PAGE)
  assert.deepEqual(verifiedRows.map((row) => row.id).sort(), [verified, blocked].sort())

  const anonymousRows = await customerRows(world.context, 'anonymous', FULL_PAGE)
  assert.deepEqual(anonymousRows.map((row) => row.id), [anonymous])

  const blockedRows = await customerRows(world.context, 'blocked', FULL_PAGE)
  assert.deepEqual(blockedRows.map((row) => row.id), [blocked])

  const allRows = await customerRows(world.context, 'all', FULL_PAGE)
  assert.equal(allRows.length, 3)
})

test('the SQL standing filter agrees with the pure email-nullness rules it replaced', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const verified = await createCustomer(world.context, { isVerified: true })
  const anonymous = await createCustomer(world.context, { isVerified: false })
  const all = await customerRows(world.context, 'all', FULL_PAGE)

  const wantVerified = all.filter((row) => isVerifiedCustomer(row)).map((row) => row.id)
  const wantAnonymous = all.filter((row) => isAnonymousCustomer(row)).map((row) => row.id)

  const verifiedRows = await customerRows(world.context, 'verified', FULL_PAGE)
  const anonymousRows = await customerRows(world.context, 'anonymous', FULL_PAGE)

  assert.deepEqual(verifiedRows.map((row) => row.id).sort(), wantVerified.sort())
  assert.deepEqual(anonymousRows.map((row) => row.id).sort(), wantAnonymous.sort())
  assert.ok(verifiedRows.some((row) => row.id === verified))
  assert.ok(anonymousRows.some((row) => row.id === anonymous))
})

test('the page offset and limit slice the customers, and rollups scope to that page', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const sellerId = await createSeller(world.context)
  const listing = await createListing(world.context, sellerId)
  const customers: CustomerId[] = []
  for (let i = 0; i < 3; i += 1) {
    customers.push(await createCustomer(world.context))
  }
  const [first, second, third] = customers
  if (first === undefined || second === undefined || third === undefined) {
    throw new Error('expected three customers')
  }
  await placedOrder(world.context, third, [listing.id])

  const firstPage = await customerRows(world.context, 'all', { offset: 0, limit: 2 })
  assert.deepEqual(firstPage.map((row) => row.id), [first, second])
  assert.equal(firstPage.every((row) => row.orderCount === 0), true)

  const secondPage = await customerRows(world.context, 'all', { offset: 2, limit: 2 })
  assert.deepEqual(secondPage.map((row) => row.id), [third])
  assert.equal(secondPage[0]?.orderCount, 1)
})

test('countCustomerRows counts every customer with the standing, not just the page', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  await createCustomer(world.context, { isVerified: true })
  await createCustomer(world.context, { isVerified: true })
  await createCustomer(world.context, { isVerified: false })

  assert.equal(await countCustomerRows(world.context), 3)
  assert.equal(await countCustomerRows(world.context, 'verified'), 2)
  assert.equal(await countCustomerRows(world.context, 'anonymous'), 1)
  assert.equal(await countCustomerRows(world.context, 'blocked'), 0)
})
