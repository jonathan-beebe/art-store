import { test } from 'node:test'
import assert from 'node:assert/strict'
import { buildTestApp, TEST_INSTANT, type TestApp } from '../../test/build-test-app.ts'
import { claimCustomerIdentity } from './claim-customer-identity.ts'
import { createAnonymousCustomer } from './create-anonymous-customer.ts'

const NOW = TEST_INSTANT.toISOString()

async function createVerifiedCustomer(
  { db }: TestApp,
  email = 'buyer@example.com',
): Promise<number> {
  const customer = await db
    .insertInto('customers')
    .values({ email, name: null, emailVerifiedAt: NOW, createdAt: NOW })
    .returning('id')
    .executeTakeFirstOrThrow()

  return customer.id
}

async function countCustomers({ db }: TestApp): Promise<number> {
  return (await db.selectFrom('customers').selectAll().execute()).length
}

async function countMerges({ db }: TestApp): Promise<number> {
  return (await db.selectFrom('customerMerges').selectAll().execute()).length
}

test('a visitor with no cookie and no account gets a new verified customer', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)

  const customer = await claimCustomerIdentity(testApp, {
    email: 'newcomer@example.com',
    currentCustomerId: null,
  })

  assert.equal(customer.email, 'newcomer@example.com')
  assert.equal(customer.emailVerifiedAt, NOW)
})

test('a visitor with no cookie signs in to the account holding the address', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const existing = await createVerifiedCustomer(testApp)

  const customer = await claimCustomerIdentity(testApp, {
    email: 'buyer@example.com',
    currentCustomerId: null,
  })

  assert.equal(customer.id, existing)
  assert.equal(await countCustomers(testApp), 1)
})

test('an anonymous visitor with no account claims the anonymous row in place', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const anonymous = await createAnonymousCustomer(testApp)

  const customer = await claimCustomerIdentity(testApp, {
    email: 'newcomer@example.com',
    currentCustomerId: anonymous.id,
  })

  assert.equal(customer.id, anonymous.id)
  assert.equal(customer.email, 'newcomer@example.com')
  assert.equal(customer.emailVerifiedAt, NOW)
  assert.equal(await countCustomers(testApp), 1)
  assert.equal(await countMerges(testApp), 0)
})

test('an anonymous visitor whose address already has an account merges into it', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const existing = await createVerifiedCustomer(testApp)
  const anonymous = await createAnonymousCustomer(testApp)

  const customer = await claimCustomerIdentity(testApp, {
    email: 'buyer@example.com',
    currentCustomerId: anonymous.id,
  })

  assert.equal(customer.id, existing)

  const merges = await testApp.db.selectFrom('customerMerges').selectAll().execute()

  assert.equal(merges.length, 1)
  assert.equal(merges[0]?.anonymousCustomerId, anonymous.id)
  assert.equal(merges[0]?.customerId, existing)
})

test('a cookie already pointing at the account writes no merge', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const existing = await createVerifiedCustomer(testApp)

  const customer = await claimCustomerIdentity(testApp, {
    email: 'buyer@example.com',
    currentCustomerId: existing,
  })

  assert.equal(customer.id, existing)
  assert.equal(await countMerges(testApp), 0)
})

test('a cookie holding another verified account signs in without merging', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const other = await createVerifiedCustomer(testApp, 'someone-else@example.com')
  const existing = await createVerifiedCustomer(testApp)

  const customer = await claimCustomerIdentity(testApp, {
    email: 'buyer@example.com',
    currentCustomerId: other,
  })

  assert.equal(customer.id, existing)
  assert.equal(await countMerges(testApp), 0)
})

test('an address a guest checkout left unverified is settled by the link', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const guest = await testApp.db
    .insertInto('customers')
    .values({ email: 'guest@example.com', name: null, emailVerifiedAt: null, createdAt: NOW })
    .returning('id')
    .executeTakeFirstOrThrow()

  const customer = await claimCustomerIdentity(testApp, {
    email: 'guest@example.com',
    currentCustomerId: null,
  })

  assert.equal(customer.id, guest.id)
  assert.equal(customer.emailVerifiedAt, NOW)
})

test('a second link leaves the original verification time alone', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const earlier = '2026-08-01T09:00:00.000Z'
  const existing = await testApp.db
    .insertInto('customers')
    .values({ email: 'buyer@example.com', name: null, emailVerifiedAt: earlier, createdAt: earlier })
    .returning('id')
    .executeTakeFirstOrThrow()

  const customer = await claimCustomerIdentity(testApp, {
    email: 'buyer@example.com',
    currentCustomerId: null,
  })

  assert.equal(customer.id, existing.id)
  assert.equal(customer.emailVerifiedAt, earlier)
})

test('an address differing only in case reaches the same account', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const existing = await createVerifiedCustomer(testApp)

  const customer = await claimCustomerIdentity(testApp, {
    email: '  BUYER@Example.com ',
    currentCustomerId: null,
  })

  assert.equal(customer.id, existing)
  assert.equal(await countCustomers(testApp), 1)
})

test('a cookie naming a customer that is gone is treated as no cookie', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)

  const customer = await claimCustomerIdentity(testApp, {
    email: 'newcomer@example.com',
    currentCustomerId: 404,
  })

  assert.equal(customer.email, 'newcomer@example.com')
  assert.equal(await countMerges(testApp), 0)
})
