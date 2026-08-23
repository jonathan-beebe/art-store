import { test } from 'node:test'
import assert from 'node:assert/strict'
import { customerStanding, canShop, type CustomerBlock } from './customer-standing.ts'

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
