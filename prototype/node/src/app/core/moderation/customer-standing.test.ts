import { test } from 'node:test'
import assert from 'node:assert/strict'
import { activeBlock, canShop, customerStanding, type CustomerBlock } from './customer-standing.ts'

function block(overrides: Partial<CustomerBlock> = {}): CustomerBlock {
  return { reason: 'abusive messages', liftedAt: null, ...overrides }
}

test('no blocks means good standing', () => {
  const standing = customerStanding([])
  assert.equal(standing.isBlocked, false)
  assert.equal(standing.reason, null)
})

test('an unlifted block reports its reason', () => {
  const standing = customerStanding([block()])
  assert.equal(standing.isBlocked, true)
  assert.equal(standing.reason, 'abusive messages')
})

test('a lifted block leaves standing unaffected', () => {
  const standing = customerStanding([block({ liftedAt: new Date('2026-01-01') })])
  assert.equal(standing.isBlocked, false)
  assert.equal(standing.reason, null)
})

test('a good standing customer can shop', () => {
  assert.equal(canShop(customerStanding([])), true)
})

test('a blocked customer cannot shop', () => {
  assert.equal(canShop(customerStanding([block()])), false)
})

test('the active block is the unlifted one, with everything else it was read with', () => {
  const lifted = { id: 1, reason: 'Chargeback fraud', liftedAt: new Date('2026-08-20T00:00:00.000Z') }
  const active = { id: 2, reason: 'Abusive messages', liftedAt: null }

  assert.equal(activeBlock([lifted, active]), active)
  assert.equal(activeBlock([lifted]), null)
  assert.equal(activeBlock([]), null)
})
