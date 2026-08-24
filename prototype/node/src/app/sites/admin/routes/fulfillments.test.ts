import { test } from 'node:test'
import assert from 'node:assert/strict'
import { buildTestApp, signInAsAdmin } from '../../../test/build-test-app.ts'
import { markShipped } from '../../../actions/fulfillments/mark-shipped.ts'
import { createCustomer, createListing, createSeller, paidOrder } from '../../../test/commerce-world.ts'
import { fixtureId } from '../../../test/fixture-ids.ts'

test('GET /admin/fulfillments lists every fulfillment with its money', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const sellerId = await createSeller({ db: testApp.db, clock: testApp.clock }, 'Blue Kiln Studio')
  const customerId = await createCustomer({ db: testApp.db, clock: testApp.clock })
  const listing = await createListing({ db: testApp.db, clock: testApp.clock }, sellerId, { priceCents: 45_000 })
  const order = await paidOrder({ db: testApp.db, clock: testApp.clock }, customerId, [listing.id])
  const fulfillment = await testApp.db
    .selectFrom('fulfillments')
    .selectAll()
    .where('orderId', '=', order.id)
    .executeTakeFirstOrThrow()

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/fulfillments',
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 200)
  assert.match(response.body, new RegExp(`data-fulfillment="${fulfillment.id}"`))
  assert.match(response.body, /Blue Kiln Studio/)
  assert.match(response.body, /data-cell="net"[^]*?\$405\.00/)
  assert.match(response.body, /data-cell="fee"[^]*?\$45\.00/)
  assert.match(response.body, new RegExp(`href="/admin/fulfillments/${fulfillment.id}"`))
  assert.match(response.body, new RegExp(`href="/admin/orders/${order.id}"`))
})

test('GET /admin/fulfillments filters by status', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const sellerId = await createSeller({ db: testApp.db, clock: testApp.clock })
  const customerId = await createCustomer({ db: testApp.db, clock: testApp.clock })
  const listing = await createListing({ db: testApp.db, clock: testApp.clock }, sellerId)
  const order = await paidOrder({ db: testApp.db, clock: testApp.clock }, customerId, [listing.id])
  const fulfillment = await testApp.db
    .selectFrom('fulfillments')
    .selectAll()
    .where('orderId', '=', order.id)
    .executeTakeFirstOrThrow()
  await markShipped(
    { db: testApp.db, clock: testApp.clock },
    { fulfillmentId: fulfillment.id, carrier: 'Royal Mail', trackingNumber: 'RM1' },
  )

  const shipped = await testApp.app.inject({
    method: 'GET',
    url: '/admin/fulfillments?status=shipped',
    cookies: admin.cookies,
  })
  const awaiting = await testApp.app.inject({
    method: 'GET',
    url: '/admin/fulfillments?status=awaiting_shipment',
    cookies: admin.cookies,
  })

  assert.match(shipped.body, new RegExp(`data-fulfillment="${fulfillment.id}"`))
  assert.doesNotMatch(awaiting.body, new RegExp(`data-fulfillment="${fulfillment.id}"`))
})

test('GET /admin/fulfillments filters by seller', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const customerId = await createCustomer({ db: testApp.db, clock: testApp.clock })
  const first = await createSeller({ db: testApp.db, clock: testApp.clock })
  const second = await createSeller({ db: testApp.db, clock: testApp.clock })
  const listingA = await createListing({ db: testApp.db, clock: testApp.clock }, first)
  const listingB = await createListing({ db: testApp.db, clock: testApp.clock }, second)
  await paidOrder({ db: testApp.db, clock: testApp.clock }, customerId, [listingA.id])
  const wantedOrder = await paidOrder({ db: testApp.db, clock: testApp.clock }, customerId, [listingB.id])
  const wanted = await testApp.db
    .selectFrom('fulfillments')
    .selectAll()
    .where('orderId', '=', wantedOrder.id)
    .executeTakeFirstOrThrow()

  const response = await testApp.app.inject({
    method: 'GET',
    url: `/admin/fulfillments?seller=${second}`,
    cookies: admin.cookies,
  })

  assert.match(response.body, new RegExp(`data-fulfillment="${wanted.id}"`))
})

test('the "all" options submit empty filters, which the table reads as no filter', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/fulfillments?status=&seller=',
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 200)
})

test('GET /admin/fulfillments/:id shows the order, seller, items, ledger entries, and status', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const sellerId = await createSeller({ db: testApp.db, clock: testApp.clock }, 'Blue Kiln Studio')
  const customerId = await createCustomer({ db: testApp.db, clock: testApp.clock })
  const listing = await createListing({ db: testApp.db, clock: testApp.clock }, sellerId, {
    title: 'Harbour at Dusk',
    priceCents: 45_000,
  })
  const order = await paidOrder({ db: testApp.db, clock: testApp.clock }, customerId, [listing.id])
  const fulfillment = await testApp.db
    .selectFrom('fulfillments')
    .selectAll()
    .where('orderId', '=', order.id)
    .executeTakeFirstOrThrow()

  const response = await testApp.app.inject({
    method: 'GET',
    url: `/admin/fulfillments/${fulfillment.id}`,
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 200)
  assert.match(response.body, new RegExp(`data-fulfillment="${fulfillment.id}"`))
  assert.match(response.body, new RegExp(`href="/admin/orders/${order.id}"`))
  assert.match(response.body, new RegExp(`href="/admin/sellers/${sellerId}"`))
  assert.match(response.body, />Blue Kiln Studio</)
  assert.match(response.body, /Harbour at Dusk/)
  assert.match(response.body, /data-fulfillment-status[^]*?Awaiting shipment/)
  assert.match(response.body, /data-cell="entry-type"[^]*?Held/)
})

test('a fulfillment id that names nobody is 404', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)

  const response = await testApp.app.inject({
    method: 'GET',
    url: `/admin/fulfillments/${fixtureId('ful', 999_999)}`,
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 404)
})

test('an order id at the fulfillment detail path is the same 404 as a fulfillment id that names nobody', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)

  const wrongPrefix = await testApp.app.inject({
    method: 'GET',
    url: `/admin/fulfillments/${fixtureId('ord', 1)}`,
    cookies: admin.cookies,
  })
  const missing = await testApp.app.inject({
    method: 'GET',
    url: `/admin/fulfillments/${fixtureId('ful', 999_999)}`,
    cookies: admin.cookies,
  })

  assert.equal(wrongPrefix.statusCode, 404)
  assert.equal(wrongPrefix.body, missing.body)
})

test('a visitor with no admin cookie is sent to sign in from the fulfillment detail page', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)

  const response = await testApp.app.inject({
    method: 'GET',
    url: `/admin/fulfillments/${fixtureId('ful', 1)}`,
  })

  assert.equal(response.statusCode, 302)
})
