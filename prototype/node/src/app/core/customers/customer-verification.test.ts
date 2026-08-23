import { test } from 'node:test'
import assert from 'node:assert/strict'
import { isAnonymousCustomer, isVerifiedCustomer } from './customer-verification.ts'

test('a customer holding an address is verified', () => {
  assert.equal(isVerifiedCustomer({ email: 'buyer@example.com' }), true)
  assert.equal(isAnonymousCustomer({ email: 'buyer@example.com' }), false)
})

test('a customer with no address is anonymous', () => {
  assert.equal(isAnonymousCustomer({ email: null }), true)
  assert.equal(isVerifiedCustomer({ email: null }), false)
})
