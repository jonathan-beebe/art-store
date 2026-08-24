import { test } from 'node:test'
import assert from 'node:assert/strict'
import { buildTestApp, signInAsAdmin } from '../../../test/build-test-app.ts'
import { createCustomer, createListing, createSeller, paidOrder, placedOrder } from '../../../test/commerce-world.ts'
import { fixtureId } from '../../../test/fixture-ids.ts'

const CUSTOMER_ID = fixtureId('cus', 3)

test('GET /admin/orders lists every order with its rollups', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const sellerId = await createSeller({ db: testApp.db, clock: testApp.clock })
  const customerId = await createCustomer({ db: testApp.db, clock: testApp.clock })
  const listing = await createListing({ db: testApp.db, clock: testApp.clock }, sellerId, { priceCents: 45_000 })
  const order = await paidOrder({ db: testApp.db, clock: testApp.clock }, customerId, [listing.id])

  const response = await testApp.app.inject({ method: 'GET', url: '/admin/orders', cookies: admin.cookies })

  assert.equal(response.statusCode, 200)
  assert.match(response.body, new RegExp(`data-order="${order.id}"`))
  assert.match(response.body, /ada@example\.test/)
  assert.match(response.body, /\$450\.00/)
  assert.match(response.body, /data-cell="fulfillment-statuses"[^]*?Awaiting shipment/)
  assert.match(response.body, new RegExp(`href="/admin/orders/${order.id}"`))
})

test('GET /admin/orders filters by status', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const sellerId = await createSeller({ db: testApp.db, clock: testApp.clock })
  const customerId = await createCustomer({ db: testApp.db, clock: testApp.clock })
  const listingA = await createListing({ db: testApp.db, clock: testApp.clock }, sellerId)
  const paid = await paidOrder({ db: testApp.db, clock: testApp.clock }, customerId, [listingA.id])
  const listingB = await createListing({ db: testApp.db, clock: testApp.clock }, sellerId)
  await placedOrder({ db: testApp.db, clock: testApp.clock }, customerId, [listingB.id])

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/orders?status=paid',
    cookies: admin.cookies,
  })

  assert.match(response.body, new RegExp(`data-order="${paid.id}"`))
})

test('GET /admin/orders filters by customer', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const sellerId = await createSeller({ db: testApp.db, clock: testApp.clock })
  const first = await createCustomer({ db: testApp.db, clock: testApp.clock })
  const second = await createCustomer({ db: testApp.db, clock: testApp.clock })
  const listingA = await createListing({ db: testApp.db, clock: testApp.clock }, sellerId)
  await placedOrder({ db: testApp.db, clock: testApp.clock }, first, [listingA.id])
  const listingB = await createListing({ db: testApp.db, clock: testApp.clock }, sellerId)
  const wanted = await placedOrder({ db: testApp.db, clock: testApp.clock }, second, [listingB.id])

  const response = await testApp.app.inject({
    method: 'GET',
    url: `/admin/orders?customer=${second}`,
    cookies: admin.cookies,
  })

  assert.match(response.body, new RegExp(`data-order="${wanted.id}"`))
})

test('the filter form remembers the submitted values', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)

  const response = await testApp.app.inject({
    method: 'GET',
    url: `/admin/orders?status=paid&customer=${CUSTOMER_ID}`,
    cookies: admin.cookies,
  })

  assert.match(response.body, /<option value="paid" selected>/)
  assert.match(response.body, new RegExp(`value="${CUSTOMER_ID}"`))
})

test('the "all" options submit empty filters, which the table reads as no filter', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/orders?status=&customer=',
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 200)
})

test('GET /admin/orders/:id shows the customer, items, payments, and fulfillments', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const sellerId = await createSeller({ db: testApp.db, clock: testApp.clock }, 'Blue Kiln Studio')
  const customerId = await createCustomer({ db: testApp.db, clock: testApp.clock })
  const customer = await testApp.db
    .selectFrom('customers')
    .selectAll()
    .where('id', '=', customerId)
    .executeTakeFirstOrThrow()
  const listing = await createListing({ db: testApp.db, clock: testApp.clock }, sellerId, {
    title: 'Harbour at Dusk',
    priceCents: 45_000,
  })
  const order = await paidOrder({ db: testApp.db, clock: testApp.clock }, customerId, [listing.id])

  const response = await testApp.app.inject({
    method: 'GET',
    url: `/admin/orders/${order.id}`,
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 200)
  assert.match(response.body, new RegExp(`data-order="${order.id}"`))
  assert.match(response.body, new RegExp(`href="/admin/customers/${customerId}"`))
  assert.ok(customer.email !== null && response.body.includes(customer.email))
  assert.match(response.body, /Harbour at Dusk/)
  assert.match(response.body, /data-cell="status"[^]*?Approved/)
  assert.match(response.body, />Blue Kiln Studio</)
  assert.match(response.body, new RegExp(`href="/admin/fulfillments/`))
})

test('an order id that names nobody is 404', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)

  const response = await testApp.app.inject({
    method: 'GET',
    url: `/admin/orders/${fixtureId('ord', 999_999)}`,
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 404)
})

test('a fulfillment id at the order detail path is the same 404 as an order id that names nobody', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)

  const wrongPrefix = await testApp.app.inject({
    method: 'GET',
    url: `/admin/orders/${fixtureId('ful', 1)}`,
    cookies: admin.cookies,
  })
  const missing = await testApp.app.inject({
    method: 'GET',
    url: `/admin/orders/${fixtureId('ord', 999_999)}`,
    cookies: admin.cookies,
  })

  assert.equal(wrongPrefix.statusCode, 404)
  assert.equal(wrongPrefix.body, missing.body)
})

test('a visitor with no admin cookie is sent to sign in from the order detail page', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)

  const response = await testApp.app.inject({
    method: 'GET',
    url: `/admin/orders/${fixtureId('ord', 1)}`,
  })

  assert.equal(response.statusCode, 302)
})
