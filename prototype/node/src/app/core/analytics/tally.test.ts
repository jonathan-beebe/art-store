import { test } from 'node:test'
import assert from 'node:assert/strict'
import { tallyOver, tallyTotal } from './tally.ts'

const STATUSES = ['draft', 'for_sale', 'sold'] as const

test('a key nothing counted still appears, at zero', () => {
  const tallies = tallyOver(STATUSES, [{ key: 'sold', count: 2 }])

  assert.deepEqual(tallies, [
    { key: 'draft', count: 0 },
    { key: 'for_sale', count: 0 },
    { key: 'sold', count: 2 },
  ])
})

test('the keys keep the domain order, not the measured order', () => {
  const tallies = tallyOver(STATUSES, [
    { key: 'sold', count: 2 },
    { key: 'draft', count: 5 },
  ])

  assert.deepEqual(
    tallies.map((tally) => tally.key),
    ['draft', 'for_sale', 'sold'],
  )
})

test('the total adds every count', () => {
  assert.equal(tallyTotal(tallyOver(STATUSES, [{ key: 'sold', count: 2 }, { key: 'draft', count: 5 }])), 7)
  assert.equal(tallyTotal([]), 0)
})
