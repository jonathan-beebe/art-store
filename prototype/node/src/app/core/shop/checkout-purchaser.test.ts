import { test } from 'node:test'
import assert from 'node:assert/strict'
import { purchaserForCheckout } from './checkout-purchaser.ts'

test('a guest buys under the address they typed', () => {
  const purchaser = purchaserForCheckout({
    customerId: 7,
    accountEmail: null,
    isAccountVerified: false,
    submittedEmail: '  Ada@Example.Test ',
  })

  assert.deepEqual(purchaser, { id: 7, email: 'ada@example.test', isEmailVerified: false })
})

test('a signed-in customer buys under the address on their account', () => {
  const purchaser = purchaserForCheckout({
    customerId: 7,
    accountEmail: 'ada@example.test',
    isAccountVerified: true,
    submittedEmail: 'someone-else@example.test',
  })

  assert.deepEqual(purchaser, { id: 7, email: 'ada@example.test', isEmailVerified: true })
})

test('an account with no address on it still buys as a guest', () => {
  const purchaser = purchaserForCheckout({
    customerId: 7,
    accountEmail: null,
    isAccountVerified: true,
    submittedEmail: 'ada@example.test',
  })

  assert.equal(purchaser.isEmailVerified, false)
})
