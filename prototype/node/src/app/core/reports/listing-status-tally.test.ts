import { test } from 'node:test'
import assert from 'node:assert/strict'
import { listingStatusTally, totalListings } from './listing-status-tally.ts'

test('it returns every status in lifecycle order', () => {
  const tally = listingStatusTally({})

  assert.deepEqual(
    tally.map((row) => row.status),
    ['draft', 'for_sale', 'sold', 'archived'],
  )
})

test('it reads the count recorded for a status', () => {
  const tally = listingStatusTally({ for_sale: 3 })

  assert.equal(tally[1]?.count, 3)
})

test('it counts a status with no listings as zero', () => {
  const tally = listingStatusTally({ for_sale: 3 })

  assert.equal(tally[0]?.count, 0)
})

test('it totals every status', () => {
  assert.equal(totalListings(listingStatusTally({ for_sale: 3, sold: 2 })), 5)
})

test('a seller with no listings totals zero', () => {
  assert.equal(totalListings(listingStatusTally({})), 0)
})
