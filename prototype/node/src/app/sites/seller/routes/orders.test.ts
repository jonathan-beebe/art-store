import { test } from 'node:test'
import assert from 'node:assert/strict'
import { buildTestApp, signInAsSeller } from '../../../test/build-test-app.ts'
import { createDeliveredFulfillment, createForSaleListing, createFulfillment } from '../test-fixtures.ts'

test('a signed-out visitor reaches no order page', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const rival = await signInAsSeller(testApp, 'rival@example.com')
  const fulfillment = await createFulfillment(testApp, rival.id)

  const index = await testApp.app.inject({ method: 'GET', url: '/seller/orders' })
  assert.equal(index.statusCode, 302)

  const show = await testApp.app.inject({ method: 'GET', url: `/seller/orders/${fulfillment.id}` })
  assert.equal(show.statusCode, 302)
})

test('an order id that is not a number is not found', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/seller/orders/not-a-number',
    cookies: seller.cookies,
  })

  assert.equal(response.statusCode, 404)
})

test("the index groups the seller's fulfillments by status", async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const waiting = await createFulfillment(testApp, seller.id)
  const delivered = await createDeliveredFulfillment(testApp, seller.id)

  const response = await testApp.app.inject({ method: 'GET', url: '/seller/orders', cookies: seller.cookies })

  assert.equal(response.statusCode, 200)
  assert.match(response.body, new RegExp(`data-group="awaiting_shipment"[\\s\\S]*data-fulfillment="${waiting.id}"`))
  assert.match(response.body, /Awaiting shipment \(1\)/)
  assert.match(response.body, new RegExp(`data-group="delivered"[\\s\\S]*data-fulfillment="${delivered.id}"`))
  assert.match(response.body, /data-group="shipped"[\s\S]*Nothing here\./)
})

test("another seller's fulfillments stay off the index", async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const rival = await signInAsSeller(testApp, 'rival@example.com')
  const rivalFulfillment = await createFulfillment(testApp, rival.id)

  const response = await testApp.app.inject({ method: 'GET', url: '/seller/orders', cookies: seller.cookies })

  assert.doesNotMatch(response.body, new RegExp(`data-fulfillment="${rivalFulfillment.id}"`))
})

test("the order page shows the address, the seller's own items, and the net", async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const listing = await createForSaleListing(testApp, seller.id, { title: 'Harbour at Dusk' })
  const fulfillment = await createFulfillment(testApp, seller.id, listing)

  const response = await testApp.app.inject({
    method: 'GET',
    url: `/seller/orders/${fulfillment.id}`,
    cookies: seller.cookies,
  })

  assert.equal(response.statusCode, 200)
  assert.match(response.body, /data-shipping-address[\s\S]*Ada Lovelace/)
  assert.match(response.body, /data-shipping-address[\s\S]*12 Analytical Way/)
  assert.match(response.body, /data-item[\s\S]*Harbour at Dusk/)
  assert.match(response.body, /data-cell="net"[^>]*>\s*\$405\.00\s*</)
})

test('an order waiting on a shipment offers the mark-shipped form', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const fulfillment = await createFulfillment(testApp, seller.id)

  const response = await testApp.app.inject({
    method: 'GET',
    url: `/seller/orders/${fulfillment.id}`,
    cookies: seller.cookies,
  })

  assert.match(response.body, new RegExp(`action="/seller/orders/${fulfillment.id}/ship"`))
  assert.match(response.body, /for="carrier"/)
  assert.match(response.body, /for="tracking_number"/)
})

test('a shipped order shows its carrier and timestamps in place of the form', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const fulfillment = await createDeliveredFulfillment(testApp, seller.id)

  const response = await testApp.app.inject({
    method: 'GET',
    url: `/seller/orders/${fulfillment.id}`,
    cookies: seller.cookies,
  })

  assert.doesNotMatch(response.body, new RegExp(`action="/seller/orders/${fulfillment.id}/ship"`))
  assert.match(response.body, /data-cell="carrier"[^>]*>Royal Mail</)
  assert.match(response.body, /data-cell="tracking_number"[^>]*>RM123456789GB</)
  assert.match(response.body, /data-cell="shipped_at"/)
  assert.match(response.body, /data-cell="delivered_at"/)
})

test("another seller's order page is not found", async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const rival = await signInAsSeller(testApp, 'rival@example.com')
  const rivalFulfillment = await createFulfillment(testApp, rival.id)

  const response = await testApp.app.inject({
    method: 'GET',
    url: `/seller/orders/${rivalFulfillment.id}`,
    cookies: seller.cookies,
  })

  assert.equal(response.statusCode, 404)
})

test('marking shipped records the carrier and the tracking number', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const fulfillment = await createFulfillment(testApp, seller.id)

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/seller/orders/${fulfillment.id}/ship`,
    cookies: seller.cookies,
    payload: { carrier: 'Royal Mail', tracking_number: 'RM123456789GB' },
  })

  assert.equal(response.statusCode, 302)
  assert.equal(response.headers.location, `/seller/orders/${fulfillment.id}`)
  const updated = await testApp.db
    .selectFrom('fulfillments')
    .selectAll()
    .where('id', '=', fulfillment.id)
    .executeTakeFirstOrThrow()
  assert.equal(updated.status, 'shipped')
  assert.equal(updated.carrier, 'Royal Mail')
  assert.equal(updated.trackingNumber, 'RM123456789GB')
  assert.notEqual(updated.shippedAt, null)
})

test('shipping the only fulfillment ships the order and tells the customer', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const fulfillment = await createFulfillment(testApp, seller.id)

  await testApp.app.inject({
    method: 'POST',
    url: `/seller/orders/${fulfillment.id}/ship`,
    cookies: seller.cookies,
    payload: { carrier: 'Royal Mail', tracking_number: 'RM123456789GB' },
  })

  const order = await testApp.db
    .selectFrom('orders')
    .selectAll()
    .where('id', '=', fulfillment.orderId)
    .executeTakeFirstOrThrow()
  assert.equal(order.status, 'shipped')

  const notification = await testApp.db
    .selectFrom('notifications')
    .selectAll()
    .where('customerId', '=', order.customerId)
    .orderBy('id', 'desc')
    .executeTakeFirstOrThrow()
  assert.equal(notification.subject, 'Order shipped')
})

test('a shipment with no carrier is refused', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const fulfillment = await createFulfillment(testApp, seller.id)

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/seller/orders/${fulfillment.id}/ship`,
    cookies: seller.cookies,
    payload: { carrier: ' ', tracking_number: 'RM123456789GB' },
  })

  assert.equal(response.statusCode, 302)
  const unchanged = await testApp.db
    .selectFrom('fulfillments')
    .selectAll()
    .where('id', '=', fulfillment.id)
    .executeTakeFirstOrThrow()
  assert.equal(unchanged.status, 'awaiting_shipment')
})

test('shipping an order that already shipped is refused', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const fulfillment = await createFulfillment(testApp, seller.id)
  await testApp.app.inject({
    method: 'POST',
    url: `/seller/orders/${fulfillment.id}/ship`,
    cookies: seller.cookies,
    payload: { carrier: 'Royal Mail', tracking_number: 'RM123456789GB' },
  })

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/seller/orders/${fulfillment.id}/ship`,
    cookies: seller.cookies,
    payload: { carrier: 'Royal Mail', tracking_number: 'RM987654321GB' },
  })

  assert.equal(response.statusCode, 302)
  const unchanged = await testApp.db
    .selectFrom('fulfillments')
    .selectAll()
    .where('id', '=', fulfillment.id)
    .executeTakeFirstOrThrow()
  assert.equal(unchanged.trackingNumber, 'RM123456789GB')
})

test('POST /orders/:id/messages opens the fulfillment thread with the order customer', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const fulfillment = await createFulfillment(testApp, seller.id)
  const order = await testApp.db
    .selectFrom('orders')
    .selectAll()
    .where('id', '=', fulfillment.orderId)
    .executeTakeFirstOrThrow()

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/seller/orders/${fulfillment.id}/messages`,
    cookies: seller.cookies,
  })

  assert.equal(response.statusCode, 302)
  assert.match(response.headers.location ?? '', /^\/seller\/messages\/\d+$/)

  const conversation = await testApp.db
    .selectFrom('conversations')
    .selectAll()
    .where('sellerId', '=', seller.id)
    .executeTakeFirstOrThrow()
  assert.equal(conversation.kind, 'fulfillment')
  assert.equal(conversation.customerId, order.customerId)
  assert.equal(conversation.fulfillmentId, fulfillment.id)

  const again = await testApp.app.inject({
    method: 'POST',
    url: `/seller/orders/${fulfillment.id}/messages`,
    cookies: seller.cookies,
  })
  assert.equal(again.headers.location, response.headers.location)
})

test("opening a message thread for another seller's order is not found", async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const rival = await signInAsSeller(testApp, 'rival@example.com')
  const rivalFulfillment = await createFulfillment(testApp, rival.id)

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/seller/orders/${rivalFulfillment.id}/messages`,
    cookies: seller.cookies,
  })

  assert.equal(response.statusCode, 404)
})

test("shipping another seller's order is not found", async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const rival = await signInAsSeller(testApp, 'rival@example.com')
  const rivalFulfillment = await createFulfillment(testApp, rival.id)

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/seller/orders/${rivalFulfillment.id}/ship`,
    cookies: seller.cookies,
    payload: { carrier: 'Royal Mail', tracking_number: 'RM123456789GB' },
  })

  assert.equal(response.statusCode, 404)
  const unchanged = await testApp.db
    .selectFrom('fulfillments')
    .selectAll()
    .where('id', '=', rivalFulfillment.id)
    .executeTakeFirstOrThrow()
  assert.equal(unchanged.status, 'awaiting_shipment')
})
