import { test } from 'node:test'
import assert from 'node:assert/strict'
import type { FastifyRequest } from 'fastify'
import { seedAdmins } from '../db/seed-admins.ts'
import { identityId, parseActorId } from './identity.ts'
import {
  browseAsAnonymousCustomer,
  buildTestApp,
  signInAsAdmin,
  signInAsCustomer,
  signInAsSeller,
} from '../test/build-test-app.ts'
import { fixtureId } from '../test/fixture-ids.ts'

const SELLER_ID = fixtureId('sel', 1)
const CUSTOMER_ID = fixtureId('cus', 1)
const ADMIN_ID = fixtureId('adm', 1)

test('one browser can be a seller, a customer, and an admin at once', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  await seedAdmins(testApp)

  const seller = await signInAsSeller(testApp)
  const customer = await signInAsCustomer(testApp)
  const admin = await signInAsAdmin(testApp)
  const cookies = { ...seller.cookies, ...customer.cookies, ...admin.cookies }

  for (const url of ['/seller/account', '/account', '/admin/account']) {
    const response = await testApp.app.inject({ method: 'GET', url, cookies })

    assert.equal(response.statusCode, 200, url)
  }
})

test('an identity cookie signed with another secret reaches nothing', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/seller/account',
    cookies: { seller_id: `${seller.id}.forged-signature` },
  })

  assert.equal(response.statusCode, 302)
})

test('a cookie carrying anything but this side\'s id is signed out, not an error', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)

  // An id from another table, the integer ids this prototype used to mint, and
  // a path traversal: every one of them is a stranger, and none is a crash.
  for (const value of [CUSTOMER_ID, '1', '../../etc/passwd', '']) {
    const response = await testApp.app.inject({
      method: 'GET',
      url: '/seller/account',
      cookies: { seller_id: testApp.app.signCookie(value) },
    })

    assert.equal(response.statusCode, 302, value)
    assert.equal(response.headers.location?.startsWith('/seller/login'), true, value)
  }
})

test('a cookie naming a seller that is gone reaches nothing', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  await testApp.db.deleteFrom('sellers').where('id', '=', seller.id).execute()

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/seller/account',
    cookies: seller.cookies,
  })

  assert.equal(response.statusCode, 302)
})

test('an anonymous customer browses but does not reach the account page', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const anonymous = await browseAsAnonymousCustomer(testApp)

  const browsing = await testApp.app.inject({
    method: 'GET',
    url: '/',
    cookies: anonymous.cookies,
  })
  const account = await testApp.app.inject({
    method: 'GET',
    url: '/account',
    cookies: anonymous.cookies,
  })

  assert.equal(browsing.statusCode, 200)
  assert.equal(account.statusCode, 302)
  assert.equal(account.headers.location, '/login?redirect_to=%2Faccount')
})

test('a storefront visit reuses the customer the cookie names', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const anonymous = await browseAsAnonymousCustomer(testApp)

  await testApp.app.inject({ method: 'GET', url: '/', cookies: anonymous.cookies })

  assert.equal((await testApp.db.selectFrom('customers').selectAll().execute()).length, 1)
})

test('a seller reaching the storefront is handed a customer identity of their own', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)

  await testApp.app.inject({ method: 'GET', url: '/', cookies: seller.cookies })

  assert.equal((await testApp.db.selectFrom('customers').selectAll().execute()).length, 1)
})

test('a cookie value that is not a prefixed id names no actor', () => {
  for (const value of ['../../etc/passwd', '12abc', '', '0', '-1', '1', '42', null, undefined]) {
    assert.equal(parseActorId('seller', value), null, String(value))
  }
})

test("a cookie holding another side's id names no actor", () => {
  assert.equal(parseActorId('seller', CUSTOMER_ID), null)
  assert.equal(parseActorId('customer', ADMIN_ID), null)
  assert.equal(parseActorId('admin', SELLER_ID), null)
})

test('a cookie value holding this side\'s id names that actor', () => {
  assert.equal(parseActorId('seller', SELLER_ID), SELLER_ID)
  assert.equal(parseActorId('customer', CUSTOMER_ID), CUSTOMER_ID)
  assert.equal(parseActorId('admin', ADMIN_ID), ADMIN_ID)
})

test('identityId unsigns a cookie at most once per request', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const signed = testApp.app.signCookie(SELLER_ID)

  let unsignCalls = 0
  const stub = {
    cookies: { seller_id: signed },
    unsignCookie(value: string) {
      unsignCalls += 1
      return testApp.app.unsignCookie(value)
    },
    parsedActorIds: null,
  } as unknown as FastifyRequest

  const first = identityId(stub, 'seller')
  const second = identityId(stub, 'seller')

  assert.equal(first, SELLER_ID)
  assert.equal(second, SELLER_ID)
  assert.equal(unsignCalls, 1)
})

test('a storefront visit and a health check trigger no seller or admin lookups', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  await seedAdmins(testApp)
  const seller = await signInAsSeller(testApp)
  const admin = await signInAsAdmin(testApp)
  const cookies = { ...seller.cookies, ...admin.cookies }

  const queriedTables: string[] = []
  const instrumented = testApp.db as unknown as { selectFrom: (table: unknown) => unknown }
  const original = testApp.db.selectFrom.bind(testApp.db)
  instrumented.selectFrom = (table: unknown) => {
    queriedTables.push(String(table))
    return original(table as never)
  }

  await testApp.app.inject({ method: 'GET', url: '/', cookies })
  await testApp.app.inject({ method: 'GET', url: '/health', cookies })

  assert.equal(queriedTables.includes('sellers'), false)
  assert.equal(queriedTables.includes('admins'), false)
})

test('the seller account page names the signed-in seller by email', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp, 'artist@example.com')

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/seller/account',
    cookies: seller.cookies,
  })

  assert.equal(response.statusCode, 200)
  assert.match(response.body, /artist@example\.com/)
})

test('the admin account page names the signed-in admin by email', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp, 'annaschmunk@pm.me')

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/account',
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 200)
  assert.match(response.body, /annaschmunk@pm\.me/)
})
