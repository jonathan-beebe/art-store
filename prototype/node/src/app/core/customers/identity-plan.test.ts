import { test } from 'node:test'
import assert from 'node:assert/strict'
import { planCustomerIdentity } from './identity-plan.ts'

test('a visitor with no history and no account gets a new verified customer', () => {
  const plan = planCustomerIdentity({ anonymousCustomerId: null, ownerOfEmailId: null })

  assert.deepEqual(plan, { action: 'createVerified' })
})

test('a visitor with no history signs in to the account holding the address', () => {
  const plan = planCustomerIdentity({ anonymousCustomerId: null, ownerOfEmailId: 7 })

  assert.deepEqual(plan, { action: 'signInExisting', verifiedCustomerId: 7 })
})

test('an anonymous visitor with no account claims the anonymous row', () => {
  const plan = planCustomerIdentity({ anonymousCustomerId: 3, ownerOfEmailId: null })

  assert.deepEqual(plan, { action: 'claimAnonymous', anonymousCustomerId: 3 })
})

test('an anonymous visitor whose address has an account merges into the account', () => {
  const plan = planCustomerIdentity({ anonymousCustomerId: 3, ownerOfEmailId: 7 })

  assert.deepEqual(plan, {
    action: 'mergeAnonymousInto',
    anonymousCustomerId: 3,
    verifiedCustomerId: 7,
  })
})

test('a cookie already pointing at the account needs no merge', () => {
  const plan = planCustomerIdentity({ anonymousCustomerId: 7, ownerOfEmailId: 7 })

  assert.deepEqual(plan, { action: 'signInExisting', verifiedCustomerId: 7 })
})
