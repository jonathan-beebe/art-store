import { test } from 'node:test'
import assert from 'node:assert/strict'
import { fixtureId } from '../../test/fixture-ids.ts'
import { planCustomerMerge } from './customer-merge-plan.ts'

test('disjoint carts merge to the union', () => {
  const plan = planCustomerMerge({
    verifiedCartLines: [{ listingId: fixtureId('lst', 1), quantity: 2 }],
    anonymousCartLines: [{ listingId: fixtureId('lst', 2), quantity: 1 }],
    verifiedFavoriteListingIds: [],
    anonymousFavoriteListingIds: [],
    stockByListing: new Map(),
  })
  assert.deepEqual(plan.cartLines, [
    { listingId: fixtureId('lst', 1), quantity: 2 },
    { listingId: fixtureId('lst', 2), quantity: 1 },
  ])
})

test('the same listing on both sides sums quantities', () => {
  const plan = planCustomerMerge({
    verifiedCartLines: [{ listingId: fixtureId('lst', 1), quantity: 2 }],
    anonymousCartLines: [{ listingId: fixtureId('lst', 1), quantity: 3 }],
    verifiedFavoriteListingIds: [],
    anonymousFavoriteListingIds: [],
    stockByListing: new Map(),
  })
  assert.deepEqual(plan.cartLines, [{ listingId: fixtureId('lst', 1), quantity: 5 }])
})

test('a summed quantity above stock clamps to stock', () => {
  const plan = planCustomerMerge({
    verifiedCartLines: [{ listingId: fixtureId('lst', 1), quantity: 2 }],
    anonymousCartLines: [{ listingId: fixtureId('lst', 1), quantity: 3 }],
    verifiedFavoriteListingIds: [],
    anonymousFavoriteListingIds: [],
    stockByListing: new Map([[fixtureId('lst', 1), 4]]),
  })
  assert.deepEqual(plan.cartLines, [{ listingId: fixtureId('lst', 1), quantity: 4 }])
})

test('stock of 0 drops the line', () => {
  const plan = planCustomerMerge({
    verifiedCartLines: [{ listingId: fixtureId('lst', 1), quantity: 2 }],
    anonymousCartLines: [],
    verifiedFavoriteListingIds: [],
    anonymousFavoriteListingIds: [],
    stockByListing: new Map([[fixtureId('lst', 1), 0]]),
  })
  assert.deepEqual(plan.cartLines, [])
})

test('a listing missing from the stock map is not clamped', () => {
  const plan = planCustomerMerge({
    verifiedCartLines: [{ listingId: fixtureId('lst', 1), quantity: 50 }],
    anonymousCartLines: [],
    verifiedFavoriteListingIds: [],
    anonymousFavoriteListingIds: [],
    stockByListing: new Map(),
  })
  assert.deepEqual(plan.cartLines, [{ listingId: fixtureId('lst', 1), quantity: 50 }])
})

test('cart ordering is verified listings first, then anonymous-only listings', () => {
  const plan = planCustomerMerge({
    verifiedCartLines: [
      { listingId: fixtureId('lst', 3), quantity: 1 },
      { listingId: fixtureId('lst', 1), quantity: 1 },
    ],
    anonymousCartLines: [
      { listingId: fixtureId('lst', 4), quantity: 1 },
      { listingId: fixtureId('lst', 2), quantity: 1 },
    ],
    verifiedFavoriteListingIds: [],
    anonymousFavoriteListingIds: [],
    stockByListing: new Map(),
  })
  assert.deepEqual(
    plan.cartLines.map((line) => line.listingId),
    [fixtureId('lst', 3), fixtureId('lst', 1), fixtureId('lst', 4), fixtureId('lst', 2)],
  )
})

test('an anonymous favorite the verified customer does not already have moves', () => {
  const plan = planCustomerMerge({
    verifiedCartLines: [],
    anonymousCartLines: [],
    verifiedFavoriteListingIds: [fixtureId('lst', 1), fixtureId('lst', 2)],
    anonymousFavoriteListingIds: [fixtureId('lst', 2), fixtureId('lst', 3)],
    stockByListing: new Map(),
  })
  assert.deepEqual(plan.favoritesToMove, [fixtureId('lst', 3)])
  assert.deepEqual(plan.favoritesToDrop, [fixtureId('lst', 2)])
})

test('anonymous favorites de-duplicate before moving', () => {
  const plan = planCustomerMerge({
    verifiedCartLines: [],
    anonymousCartLines: [],
    verifiedFavoriteListingIds: [],
    anonymousFavoriteListingIds: [fixtureId('lst', 1), fixtureId('lst', 1), fixtureId('lst', 2)],
    stockByListing: new Map(),
  })
  assert.deepEqual(plan.favoritesToMove, [fixtureId('lst', 1), fixtureId('lst', 2)])
})

test('a verified customer with no favorites moves every anonymous favorite', () => {
  const plan = planCustomerMerge({
    verifiedCartLines: [],
    anonymousCartLines: [],
    verifiedFavoriteListingIds: [],
    anonymousFavoriteListingIds: [fixtureId('lst', 1), fixtureId('lst', 2)],
    stockByListing: new Map(),
  })
  assert.deepEqual(plan.favoritesToMove, [fixtureId('lst', 1), fixtureId('lst', 2)])
  assert.deepEqual(plan.favoritesToDrop, [])
})

test('an anonymous customer with no favorites moves and drops nothing', () => {
  const plan = planCustomerMerge({
    verifiedCartLines: [],
    anonymousCartLines: [],
    verifiedFavoriteListingIds: [fixtureId('lst', 1), fixtureId('lst', 2)],
    anonymousFavoriteListingIds: [],
    stockByListing: new Map(),
  })
  assert.deepEqual(plan.favoritesToMove, [])
  assert.deepEqual(plan.favoritesToDrop, [])
})

test('empty inputs give empty outputs', () => {
  const plan = planCustomerMerge({
    verifiedCartLines: [],
    anonymousCartLines: [],
    verifiedFavoriteListingIds: [],
    anonymousFavoriteListingIds: [],
    stockByListing: new Map(),
  })
  assert.deepEqual(plan.cartLines, [])
  assert.deepEqual(plan.favoritesToMove, [])
  assert.deepEqual(plan.favoritesToDrop, [])
})
