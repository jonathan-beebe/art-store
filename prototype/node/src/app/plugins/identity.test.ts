import { test } from 'node:test'
import assert from 'node:assert/strict'
import { seedAdmins } from '../db/seed-admins.ts'
import {
  browseAsAnonymousCustomer,
  buildTestApp,
  signInAsAdmin,
  signInAsCustomer,
  signInAsSeller,
} from '../test/build-test-app.ts'

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
