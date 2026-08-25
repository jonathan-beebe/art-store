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

test('a full page of orders shows 25 and a link to the next page, which shows the rest', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const context = { db: testApp.db, clock: testApp.clock }
  const sellerId = await createSeller(context)
  const customerId = await createCustomer(context)

  for (let i = 0; i < 27; i += 1) {
    const listing = await createListing(context, sellerId)
    await placedOrder(context, customerId, [listing.id])
  }

  const firstPage = await testApp.app.inject({ method: 'GET', url: '/admin/orders', cookies: admin.cookies })
  assert.equal((firstPage.body.match(/data-order="/g) ?? []).length, 25)
  assert.match(firstPage.body, /page=2/)

  const secondPage = await testApp.app.inject({
    method: 'GET',
    url: '/admin/orders?page=2',
    cookies: admin.cookies,
  })
  assert.equal((secondPage.body.match(/data-order="/g) ?? []).length, 2)
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
  // Same `sid` on both requests: each page renders its own CSRF token from
  // whichever session asked for it, so two genuinely identical pages still
  // need the same session behind them to come out byte for byte equal.
  const cookies = { sid: `ses_${'0'.repeat(26)}`, ...admin.cookies }

  const wrongPrefix = await testApp.app.inject({
    method: 'GET',
    url: `/admin/orders/${fixtureId('ful', 1)}`,
    cookies,
  })
  const missing = await testApp.app.inject({
    method: 'GET',
    url: `/admin/orders/${fixtureId('ord', 999_999)}`,
    cookies,
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

test('the order page offers Cancel while the order is unpaid', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const context = { db: testApp.db, clock: testApp.clock }
  const sellerId = await createSeller(context)
  const customerId = await createCustomer(context)
  const listing = await createListing(context, sellerId)
  const order = await placedOrder(context, customerId, [listing.id])

  const response = await testApp.app.inject({
    method: 'GET',
    url: `/admin/orders/${order.id}`,
    cookies: admin.cookies,
  })

  assert.match(response.body, new RegExp(`action="/admin/orders/${order.id}/cancel"`))
})

test('the order page offers no Refund form for an unpaid order\'s fulfillment', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const context = { db: testApp.db, clock: testApp.clock }
  const sellerId = await createSeller(context)
  const customerId = await createCustomer(context)
  const listing = await createListing(context, sellerId)
  const order = await placedOrder(context, customerId, [listing.id])
  const fulfillment = await testApp.db
    .selectFrom('fulfillments')
    .selectAll()
    .where('orderId', '=', order.id)
    .executeTakeFirstOrThrow()

  const response = await testApp.app.inject({
    method: 'GET',
    url: `/admin/orders/${order.id}`,
    cookies: admin.cookies,
  })

  assert.doesNotMatch(response.body, new RegExp(`action="/admin/fulfillments/${fulfillment.id}/refund"`))
})

test('the order page drops Cancel once the order is paid and offers Refund instead', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const context = { db: testApp.db, clock: testApp.clock }
  const sellerId = await createSeller(context)
  const customerId = await createCustomer(context)
  const listing = await createListing(context, sellerId)
  const order = await paidOrder(context, customerId, [listing.id])
  const fulfillment = await testApp.db
    .selectFrom('fulfillments')
    .selectAll()
    .where('orderId', '=', order.id)
    .executeTakeFirstOrThrow()

  const response = await testApp.app.inject({
    method: 'GET',
    url: `/admin/orders/${order.id}`,
    cookies: admin.cookies,
  })

  assert.doesNotMatch(response.body, new RegExp(`action="/admin/orders/${order.id}/cancel"`))
  assert.match(response.body, new RegExp(`action="/admin/fulfillments/${fulfillment.id}/refund"`))
})

test('POST /admin/orders/:id/cancel cancels an unpaid order and tells both sides', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const context = { db: testApp.db, clock: testApp.clock }
  const sellerId = await createSeller(context)
  const customerId = await createCustomer(context)
  const listing = await createListing(context, sellerId)
  const order = await placedOrder(context, customerId, [listing.id])

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/admin/orders/${order.id}/cancel`,
    cookies: admin.cookies,
    payload: { reason: 'The buyer asked us to.' },
  })

  assert.equal(response.statusCode, 302)
  const cancelled = await testApp.db
    .selectFrom('orders')
    .select('status')
    .where('id', '=', order.id)
    .executeTakeFirstOrThrow()
  assert.equal(cancelled.status, 'cancelled')

  const told = await testApp.db.selectFrom('notifications').selectAll().execute()
  assert.equal(told.filter((row) => row.customerId === customerId).length, 1)
  assert.equal(told.filter((row) => row.sellerId === sellerId).length, 1)
})

test('POST /admin/orders/:id/cancel refuses a paid order', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const context = { db: testApp.db, clock: testApp.clock }
  const sellerId = await createSeller(context)
  const customerId = await createCustomer(context)
  const listing = await createListing(context, sellerId)
  const order = await paidOrder(context, customerId, [listing.id])

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/admin/orders/${order.id}/cancel`,
    cookies: admin.cookies,
    payload: { reason: 'Too late.' },
  })

  assert.equal(response.statusCode, 302)
  const unchanged = await testApp.db
    .selectFrom('orders')
    .select('status')
    .where('id', '=', order.id)
    .executeTakeFirstOrThrow()
  assert.equal(unchanged.status, 'paid')
})

test('POST /admin/orders/:id/cancel refuses an empty reason', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const context = { db: testApp.db, clock: testApp.clock }
  const sellerId = await createSeller(context)
  const customerId = await createCustomer(context)
  const listing = await createListing(context, sellerId)
  const order = await placedOrder(context, customerId, [listing.id])

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/admin/orders/${order.id}/cancel`,
    cookies: admin.cookies,
    payload: { reason: '' },
  })

  assert.equal(response.statusCode, 302)
  const unchanged = await testApp.db
    .selectFrom('orders')
    .select('status')
    .where('id', '=', order.id)
    .executeTakeFirstOrThrow()
  assert.equal(unchanged.status, 'awaiting_payment')
})

test('POST /admin/orders/:id/cancel on an id that names nothing is not found', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/admin/orders/${fixtureId('ord', 404)}/cancel`,
    cookies: admin.cookies,
    payload: { reason: 'Nobody home.' },
  })

  assert.equal(response.statusCode, 404)
})

test('refunding from the order page lands back on the order and lists the refund', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const context = { db: testApp.db, clock: testApp.clock }
  const sellerId = await createSeller(context)
  const customerId = await createCustomer(context)
  const listing = await createListing(context, sellerId, { priceCents: 45_000 })
  const order = await paidOrder(context, customerId, [listing.id])
  const fulfillment = await testApp.db
    .selectFrom('fulfillments')
    .selectAll()
    .where('orderId', '=', order.id)
    .executeTakeFirstOrThrow()

  const refunded = await testApp.app.inject({
    method: 'POST',
    url: `/admin/fulfillments/${fulfillment.id}/refund`,
    cookies: admin.cookies,
    payload: { reason: 'The seller went silent.', redirect_to: `/admin/orders/${order.id}` },
  })

  assert.equal(refunded.headers.location, `/admin/orders/${order.id}`)

  const response = await testApp.app.inject({
    method: 'GET',
    url: `/admin/orders/${order.id}`,
    cookies: admin.cookies,
  })

  assert.match(response.body, /data-refund=/)
  assert.match(response.body, /data-cell="reason"[\s\S]*The seller went silent\./)
  assert.match(response.body, /data-order-refunded[^>]*>\s*\$450\.00\s*</)
  assert.match(response.body, /data-order-status[^>]*>\s*Refunded\s*</)
})
