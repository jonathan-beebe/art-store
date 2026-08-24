import { test } from 'node:test'
import assert from 'node:assert/strict'
import { fixtureId } from '../../test/fixture-ids.ts'
import { planCustomerIdentity } from './identity-plan.ts'

test('a visitor with no history and no account gets a new verified customer', () => {
  const plan = planCustomerIdentity({ anonymousCustomerId: null, ownerOfEmailId: null })

  assert.deepEqual(plan, { action: 'createVerified' })
})

test('a visitor with no history signs in to the account holding the address', () => {
  const plan = planCustomerIdentity({ anonymousCustomerId: null, ownerOfEmailId: fixtureId('cus', 7) })

  assert.deepEqual(plan, { action: 'signInExisting', verifiedCustomerId: fixtureId('cus', 7) })
})

test('an anonymous visitor with no account claims the anonymous row', () => {
  const plan = planCustomerIdentity({ anonymousCustomerId: fixtureId('cus', 3), ownerOfEmailId: null })

  assert.deepEqual(plan, { action: 'claimAnonymous', anonymousCustomerId: fixtureId('cus', 3) })
})

test('an anonymous visitor whose address has an account merges into the account', () => {
  const plan = planCustomerIdentity({ anonymousCustomerId: fixtureId('cus', 3), ownerOfEmailId: fixtureId('cus', 7) })

  assert.deepEqual(plan, {
    action: 'mergeAnonymousInto',
    anonymousCustomerId: fixtureId('cus', 3),
    verifiedCustomerId: fixtureId('cus', 7),
  })
})

test('a cookie already pointing at the account needs no merge', () => {
  const plan = planCustomerIdentity({ anonymousCustomerId: fixtureId('cus', 7), ownerOfEmailId: fixtureId('cus', 7) })

  assert.deepEqual(plan, { action: 'signInExisting', verifiedCustomerId: fixtureId('cus', 7) })
})
