import { test } from 'node:test'
import assert from 'node:assert/strict'
import { buildTestApp, TEST_INSTANT } from '../../test/build-test-app.ts'
import { createAnonymousCustomer } from './create-anonymous-customer.ts'

test('it returns a customer with no address and no verification time, stamped at the clock\'s now', async (t) => {
  const { db, clock, close } = await buildTestApp()
  t.after(close)

  const customer = await createAnonymousCustomer({ db, clock })

  assert.equal(customer.email, null)
  assert.equal(customer.emailVerifiedAt, null)
  assert.equal(customer.createdAt, TEST_INSTANT.toISOString())
})

test('two calls create two distinct rows', async (t) => {
  const { db, clock, close } = await buildTestApp()
  t.after(close)

  const first = await createAnonymousCustomer({ db, clock })
  const second = await createAnonymousCustomer({ db, clock })

  assert.notEqual(first.id, second.id)
})
