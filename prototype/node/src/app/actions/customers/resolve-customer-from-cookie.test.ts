import { test } from 'node:test'
import assert from 'node:assert/strict'
import { buildTestApp, TEST_INSTANT } from '../../test/build-test-app.ts'
import { createAnonymousCustomer } from './create-anonymous-customer.ts'
import { resolveCustomerFromCookie } from './resolve-customer-from-cookie.ts'
import { toTimestamp } from '../../db/timestamp.ts'

test('it finds the customer the cookie names', async (t) => {
  const { db, clock, close } = await buildTestApp()
  t.after(close)
  const customer = await createAnonymousCustomer({ db, clock })

  const found = await resolveCustomerFromCookie({ db }, customer.id)

  assert.equal(found?.id, customer.id)
})

test('a cookie left holding a merged id resolves forward to the customer the history moved to', async (t) => {
  const { db, clock, close } = await buildTestApp()
  t.after(close)
  const anonymous = await createAnonymousCustomer({ db, clock })
  const verified = await createAnonymousCustomer({ db, clock })
  await db
    .insertInto('customerMerges')
    .values({
      anonymousCustomerId: anonymous.id,
      customerId: verified.id,
      createdAt: toTimestamp(TEST_INSTANT),
    })
    .execute()

  const found = await resolveCustomerFromCookie({ db }, anonymous.id)

  assert.equal(found?.id, verified.id)
})

test('it resolves nothing when the cookie named nobody', async (t) => {
  const { db, close } = await buildTestApp()
  t.after(close)

  assert.equal(await resolveCustomerFromCookie({ db }, null), null)
})

test('it resolves nothing for a customer that no longer exists', async (t) => {
  const { db, close } = await buildTestApp()
  t.after(close)

  const found = await resolveCustomerFromCookie({ db }, 999999)

  assert.equal(found, null)
})

