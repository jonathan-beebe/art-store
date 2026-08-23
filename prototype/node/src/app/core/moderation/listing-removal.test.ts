import { test } from 'node:test'
import assert from 'node:assert/strict'
import { activeRemoval, canLiftRemoval, type ListingRemoval } from './listing-removal.ts'

function removal(overrides: Partial<ListingRemoval> = {}): ListingRemoval {
  return { kind: 'temporary', reason: 'reported artwork', liftedAt: null, ...overrides }
}

test('no removals means no active removal', () => {
  assert.equal(activeRemoval([]), null)
})

test('an unlifted removal is active', () => {
  const active = removal()
  assert.equal(activeRemoval([active]), active)
})

test('a lifted removal is not active', () => {
  assert.equal(activeRemoval([removal({ liftedAt: new Date('2026-01-01') })]), null)
})

test('the first unlifted removal among several is active', () => {
  const lifted = removal({ liftedAt: new Date('2026-01-01') })
  const active = removal({ reason: 'second report' })
  assert.equal(activeRemoval([lifted, active]), active)
})

test('a temporary removal can be lifted', () => {
  assert.equal(canLiftRemoval('temporary'), true)
})

test('a permanent removal cannot be lifted', () => {
  assert.equal(canLiftRemoval('permanent'), false)
})
