import { test } from 'node:test'
import assert from 'node:assert/strict'
import { blockCustomer } from '../../../actions/moderation/block-customer.ts'
import { mustSucceed } from '../../../core/refusal.ts'
import {
  buildTestApp,
  signInAsAdmin,
  signInAsCustomer,
  signInAsSeller,
} from '../../../test/build-test-app.ts'
import { createAdmin, createCustomer, createListing, createSeller, placedOrder } from '../../../test/commerce-world.ts'

test('the customers list renders for a signed-in admin, one row per customer', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const context = { db: testApp.db, clock: testApp.clock }

  const sellerId = await createSeller(context)
  const listing = await createListing(context, sellerId)
  const customerId = await createCustomer(context, { isVerified: true })
  await placedOrder(context, customerId, [listing.id])

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/customers',
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 200)
  assert.match(response.body, new RegExp(`data-customer="${customerId}"`))
  assert.match(response.body, /data-cell="orders"[^]*?>1</)
  assert.match(response.body, /data-cell="blocked"[^]*?No</)
})

test('an anonymous customer shows as Anonymous', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const context = { db: testApp.db, clock: testApp.clock }

  await createCustomer(context, { isVerified: false })

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/customers',
    cookies: admin.cookies,
  })

  assert.match(response.body, />Anonymous</)
})

test('a visitor with no admin cookie is sent to sign in', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)

  const response = await testApp.app.inject({ method: 'GET', url: '/admin/customers' })

  assert.equal(response.statusCode, 302)
})

test('a seller or customer cookie does not open the admin customers page', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const customer = await signInAsCustomer(testApp)

  const asSeller = await testApp.app.inject({
    method: 'GET',
    url: '/admin/customers',
    cookies: seller.cookies,
  })
  const asCustomer = await testApp.app.inject({
    method: 'GET',
    url: '/admin/customers',
    cookies: customer.cookies,
  })

  assert.equal(asSeller.statusCode, 302)
  assert.equal(asCustomer.statusCode, 302)
})

test('the standing filter narrows the table to blocked customers', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const context = { db: testApp.db, clock: testApp.clock }

  const unblocked = await createCustomer(context, { isVerified: true })
  const blocked = await createCustomer(context, { isVerified: true })
  const adminId = await createAdmin(context)
  mustSucceed(await blockCustomer(context, { customerId: blocked, adminId, reason: 'Chargeback fraud.' }))

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/customers?standing=blocked',
    cookies: admin.cookies,
  })

  assert.match(response.body, new RegExp(`data-customer="${blocked}"`))
  assert.doesNotMatch(response.body, new RegExp(`data-customer="${unblocked}"`))
  assert.match(response.body, /value="blocked" selected/)
})

test('the standing filter narrows the table to verified customers', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const context = { db: testApp.db, clock: testApp.clock }

  const verified = await createCustomer(context, { isVerified: true })
  const anonymous = await createCustomer(context, { isVerified: false })

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/customers?standing=verified',
    cookies: admin.cookies,
  })

  assert.match(response.body, new RegExp(`data-customer="${verified}"`))
  assert.doesNotMatch(response.body, new RegExp(`data-customer="${anonymous}"`))
})

test('the standing filter narrows the table to anonymous customers', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const context = { db: testApp.db, clock: testApp.clock }

  const verified = await createCustomer(context, { isVerified: true })
  const anonymous = await createCustomer(context, { isVerified: false })

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/customers?standing=anonymous',
    cookies: admin.cookies,
  })

  assert.match(response.body, new RegExp(`data-customer="${anonymous}"`))
  assert.doesNotMatch(response.body, new RegExp(`data-customer="${verified}"`))
})

test('a full page of customers shows 25 and a link to the next page, which shows the rest', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const context = { db: testApp.db, clock: testApp.clock }

  for (let i = 0; i < 27; i += 1) {
    await createCustomer(context)
  }

  const firstPage = await testApp.app.inject({ method: 'GET', url: '/admin/customers', cookies: admin.cookies })
  assert.equal((firstPage.body.match(/data-customer="/g) ?? []).length, 25)
  assert.match(firstPage.body, /page=2/)

  const secondPage = await testApp.app.inject({
    method: 'GET',
    url: '/admin/customers?page=2',
    cookies: admin.cookies,
  })
  assert.equal((secondPage.body.match(/data-customer="/g) ?? []).length, 2)
})

test('the customer detail page shows an unblocked customer with a block form', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const context = { db: testApp.db, clock: testApp.clock }

  const sellerId = await createSeller(context)
  const listing = await createListing(context, sellerId, { title: 'Harbour at Dusk' })
  const customerId = await createCustomer(context, { isVerified: true })
  await placedOrder(context, customerId, [listing.id])

  const response = await testApp.app.inject({
    method: 'GET',
    url: `/admin/customers/${customerId}`,
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 200)
  assert.match(response.body, /data-standing="unblocked"/)
  assert.match(response.body, new RegExp(`<form method="post" action="/admin/customers/${customerId}/blocks"`))
  assert.match(
    response.body,
    new RegExp(`<input type="hidden" name="redirect_to" value="/admin/customers/${customerId}" />`),
  )
  assert.match(response.body, /<textarea[^]*?name="reason"[^]*?required/)
  assert.doesNotMatch(response.body, new RegExp(`/admin/customers/${customerId}/blocks/lift`))
})

test('the customer detail page titles a verified customer by their address', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const context = { db: testApp.db, clock: testApp.clock }

  const customerId = await createCustomer(context, { isVerified: true })
  const customer = await testApp.db
    .selectFrom('customers')
    .selectAll()
    .where('id', '=', customerId)
    .executeTakeFirstOrThrow()

  const response = await testApp.app.inject({
    method: 'GET',
    url: `/admin/customers/${customerId}`,
    cookies: admin.cookies,
  })

  assert.match(response.body, new RegExp(`<title>${customer.email} — Admin</title>`))
})

test('the customer detail page titles an anonymous customer Guest <id>', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const context = { db: testApp.db, clock: testApp.clock }

  const customerId = await createCustomer(context, { isVerified: false })

  const response = await testApp.app.inject({
    method: 'GET',
    url: `/admin/customers/${customerId}`,
    cookies: admin.cookies,
  })

  assert.match(response.body, new RegExp(`<title>Guest ${customerId} — Admin</title>`))
})

test('the customer detail page shows a blocked customer with a lift form and the reason', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const context = { db: testApp.db, clock: testApp.clock }

  const customerId = await createCustomer(context, { isVerified: true })
  const adminId = await createAdmin(context)
  mustSucceed(await blockCustomer(context, { customerId, adminId, reason: 'Chargeback fraud.' }))

  const response = await testApp.app.inject({
    method: 'GET',
    url: `/admin/customers/${customerId}`,
    cookies: admin.cookies,
  })

  assert.match(response.body, /data-standing="blocked"/)
  assert.match(response.body, /Chargeback fraud\./)
  assert.match(response.body, new RegExp(`action="/admin/customers/${customerId}/blocks/lift"`))
  assert.match(
    response.body,
    new RegExp(`<input type="hidden" name="redirect_to" value="/admin/customers/${customerId}" />`),
  )
})

test('a customer id that names nobody is 404', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/customers/999999',
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 404)
})

test('an empty standing filter reads as every customer, not as a refused value', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/customers?standing=',
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 200)
})
