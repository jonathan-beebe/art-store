import { test } from 'node:test'
import assert from 'node:assert/strict'
import { platformTallies } from './platform-tallies.ts'
import {
  createCustomer,
  createListing,
  createSeller,
  openCommerceWorld,
  paidOrder,
  placedOrder,
} from '../../../test/commerce-world.ts'

test('an empty platform counts nobody and still names every state', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const tallies = await platformTallies(world.context)

  assert.equal(tallies.sellerCount, 0)
  assert.deepEqual(tallies.customers, { verified: 0, anonymous: 0 })
  assert.deepEqual(
    tallies.listings.map((tally) => tally.key),
    ['draft', 'for_sale', 'sold', 'archived'],
  )
  assert.equal(
    tallies.listings.every((tally) => tally.count === 0),
    true,
  )
  assert.equal(tallies.orders.length, 8)
  assert.equal(tallies.fulfillments.length, 3)
})

test('customers are split by whether they have given an address', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  await createCustomer(world.context)
  await createCustomer(world.context)
  await createCustomer(world.context, { isVerified: false })

  assert.deepEqual((await platformTallies(world.context)).customers, {
    verified: 2,
    anonymous: 1,
  })
})

test('listings, orders, and fulfillments are counted by status', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  const sellerId = await createSeller(world.context)
  const customerId = await createCustomer(world.context)
  // Placing an order takes the stock, so a listing that is to stay for sale needs a second.
  const forSale = await createListing(world.context, sellerId, { quantity: 2 })
  await createListing(world.context, sellerId, { status: 'draft' })
  const sold = await createListing(world.context, sellerId)

  await paidOrder(world.context, customerId, [sold.id])
  await placedOrder(world.context, customerId, [forSale.id])

  const tallies = await platformTallies(world.context)
  const counted = (tallies: readonly { key: string; count: number }[], key: string): number =>
    tallies.find((tally) => tally.key === key)?.count ?? 0

  assert.equal(tallies.sellerCount, 1)
  assert.equal(counted(tallies.listings, 'draft'), 1)
  assert.equal(counted(tallies.listings, 'sold'), 1)
  assert.equal(counted(tallies.listings, 'for_sale'), 1)
  assert.equal(counted(tallies.orders, 'paid'), 1)
  assert.equal(counted(tallies.orders, 'awaiting_payment'), 1)
  assert.equal(counted(tallies.fulfillments, 'awaiting_shipment'), 2)
})
