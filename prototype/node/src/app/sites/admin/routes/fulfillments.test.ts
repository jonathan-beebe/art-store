import { test } from 'node:test'
import assert from 'node:assert/strict'
import { buildTestApp, signInAsAdmin } from '../../../test/build-test-app.ts'
import { markShipped } from '../../../actions/fulfillments/mark-shipped.ts'
import { createCustomer, createListing, createSeller, paidOrder, placedOrder } from '../../../test/commerce-world.ts'
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

test('GET /admin/fulfillments/:id offers no Refund form for an unpaid order', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  const sellerId = await createSeller({ db: testApp.db, clock: testApp.clock })
  const customerId = await createCustomer({ db: testApp.db, clock: testApp.clock })
  const listing = await createListing({ db: testApp.db, clock: testApp.clock }, sellerId)
  const order = await placedOrder({ db: testApp.db, clock: testApp.clock }, customerId, [listing.id])
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
  assert.doesNotMatch(response.body, new RegExp(`action="/admin/fulfillments/${fulfillment.id}/refund"`))
})

test('GET /admin/fulfillments/:id offers a Refund form for a paid order', async (t) => {
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

  const response = await testApp.app.inject({
    method: 'GET',
    url: `/admin/fulfillments/${fulfillment.id}`,
    cookies: admin.cookies,
  })

  assert.match(response.body, new RegExp(`action="/admin/fulfillments/${fulfillment.id}/refund"`))
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

test('POST /admin/fulfillments/:id/refund reverses the sale without restoring stock', async (t) => {
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
  await markShipped(context, {
    fulfillmentId: fulfillment.id,
    carrier: 'Royal Mail',
    trackingNumber: 'RM123456789GB',
  })

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/admin/fulfillments/${fulfillment.id}/refund`,
    cookies: admin.cookies,
    payload: { reason: 'The customer never received it.' },
  })

  assert.equal(response.statusCode, 302)
  const refunded = await testApp.db
    .selectFrom('fulfillments')
    .select('status')
    .where('id', '=', fulfillment.id)
    .executeTakeFirstOrThrow()
  assert.equal(refunded.status, 'refunded')

  const refund = await testApp.db
    .selectFrom('refunds')
    .selectAll()
    .where('fulfillmentId', '=', fulfillment.id)
    .executeTakeFirstOrThrow()
  assert.equal(refund.issuedByType, 'admin')
  assert.equal(refund.issuedById, admin.id)
  assert.equal(refund.amountCents, 45_000)

  const stock = await testApp.db
    .selectFrom('listings')
    .select(['quantity', 'status'])
    .where('id', '=', listing.id)
    .executeTakeFirstOrThrow()
  assert.deepEqual(stock, { quantity: 0, status: 'sold' })
})

test('POST /admin/fulfillments/:id/refund refuses a second refund', async (t) => {
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

  const url = `/admin/fulfillments/${fulfillment.id}/refund`
  await testApp.app.inject({ method: 'POST', url, cookies: admin.cookies, payload: { reason: 'First.' } })
  const second = await testApp.app.inject({
    method: 'POST',
    url,
    cookies: admin.cookies,
    payload: { reason: 'Second.' },
  })

  assert.equal(second.statusCode, 302)
  const refunds = await testApp.db
    .selectFrom('refunds')
    .selectAll()
    .where('fulfillmentId', '=', fulfillment.id)
    .execute()
  assert.equal(refunds.length, 1)
})

test('POST /admin/fulfillments/:id/refund refuses an empty reason', async (t) => {
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

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/admin/fulfillments/${fulfillment.id}/refund`,
    cookies: admin.cookies,
    payload: { reason: '  ' },
  })

  assert.equal(response.statusCode, 302)
  assert.deepEqual(await testApp.db.selectFrom('refunds').selectAll().execute(), [])
})

test('POST /admin/fulfillments/:id/refund on an id that names nothing is not found', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/admin/fulfillments/${fixtureId('ful', 404)}/refund`,
    cookies: admin.cookies,
    payload: { reason: 'Nobody home.' },
  })

  assert.equal(response.statusCode, 404)
})

test('the fulfillment page shows the refund once it has been issued', async (t) => {
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
  await testApp.app.inject({
    method: 'POST',
    url: `/admin/fulfillments/${fulfillment.id}/refund`,
    cookies: admin.cookies,
    payload: { reason: 'The customer never received it.' },
  })

  const response = await testApp.app.inject({
    method: 'GET',
    url: `/admin/fulfillments/${fulfillment.id}`,
    cookies: admin.cookies,
  })

  assert.match(response.body, /data-cell="reason"[\s\S]*The customer never received it\./)
  assert.match(response.body, /data-cell="issued-by"[^>]*>\s*Admin\s*</)
  assert.match(response.body, /data-ledger-entry[\s\S]*Refunded/)
})
