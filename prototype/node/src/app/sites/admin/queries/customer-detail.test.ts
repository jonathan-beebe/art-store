import { test } from 'node:test'
import assert from 'node:assert/strict'
import { customerDetail } from './customer-detail.ts'
import { blockCustomer } from '../../../actions/moderation/block-customer.ts'
import { mergeAnonymousCustomer } from '../../../actions/customers/merge-anonymous-customer.ts'
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

test('an id that names no customer reads null', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  assert.equal(await customerDetail(world.context, 999), null)
})

test('a fresh customer reads empty lists and no block', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const customerId = await createCustomer(world.context)

  const detail = await customerDetail(world.context, customerId)

  assert.equal(detail?.customer.id, customerId)
  assert.deepEqual(detail?.orders, [])
  assert.deepEqual(detail?.favorites, [])
  assert.deepEqual(detail?.cartLines, [])
  assert.deepEqual(detail?.merges, [])
  assert.equal(detail?.block, null)
})

test('orders, favorites, and cart lines all read back', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const sellerId = await createSeller(world.context)
  const orderedListing = await createListing(world.context, sellerId, { title: 'Harbour at Dusk' })
  const favoritedListing = await createListing(world.context, sellerId, { title: 'Quiet Orchard' })
  const cartedListing = await createListing(world.context, sellerId, { title: 'Winter Field' })
  const customerId = await createCustomer(world.context)

  await placedOrder(world.context, customerId, [orderedListing.id])
  await cartHolding(world.context, customerId, [cartedListing.id])
  await world.db
    .insertInto('favorites')
    .values({ customerId, listingId: favoritedListing.id, createdAt: toTimestamp(new Date()) })
    .execute()

  const detail = await customerDetail(world.context, customerId)

  assert.equal(detail?.orders.length, 1)
  assert.equal(detail?.favorites[0]?.title, 'Quiet Orchard')
  assert.equal(detail?.cartLines[0]?.title, 'Winter Field')
  assert.equal(detail?.cartLines[0]?.quantity, 1)
})

test('a block shows its reason, and merges show both directions', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const adminId = await createAdmin(world.context)
  const anonymous = await createCustomer(world.context, { isVerified: false })
  const verified = await createCustomer(world.context, { isVerified: true })
  const other = await createCustomer(world.context, { isVerified: false })

  await mergeAnonymousCustomer(world.context, {
    anonymousCustomerId: anonymous,
    verifiedCustomerId: verified,
  })
  await mergeAnonymousCustomer(world.context, {
    anonymousCustomerId: other,
    verifiedCustomerId: verified,
  })
  await blockCustomer(world.context, { customerId: verified, adminId, reason: 'Chargeback fraud.' })

  const detail = await customerDetail(world.context, verified)
  assert.ok(detail)
  assert.ok(detail.block)

  assert.equal(detail.block.reason, 'Chargeback fraud.')
  assert.equal(detail.merges.length, 2)
  assert.ok(detail.merges.every((merge) => merge.direction === 'into'))
  assert.deepEqual(
    detail.merges.map((merge) => merge.otherCustomerId).sort(),
    [anonymous, other].sort(),
  )

  const anonymousDetail = await customerDetail(world.context, anonymous)
  assert.ok(anonymousDetail)
  assert.equal(anonymousDetail.merges.length, 1)
  const [firstMerge] = anonymousDetail.merges
  assert.ok(firstMerge)
  assert.equal(firstMerge.direction, 'out_of')
  assert.equal(firstMerge.otherCustomerId, verified)
})
