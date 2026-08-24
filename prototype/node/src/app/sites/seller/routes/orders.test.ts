import { test } from 'node:test'
import assert from 'node:assert/strict'
import { buildTestApp, signInAsSeller } from '../../../test/build-test-app.ts'
import { buildLoggedTestApp } from '../../../test/log-lines.ts'
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

test('a bodiless ship POST is refused instead of failing', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const fulfillment = await createFulfillment(testApp, seller.id)

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/seller/orders/${fulfillment.id}/ship`,
    cookies: seller.cookies,
  })

  assert.equal(response.statusCode, 422)
  assert.match(response.body, /data-field-error="carrier"[^>]*>Enter the carrier\./)
  assert.match(response.body, /data-field-error="tracking_number"[^>]*>Enter the tracking number\./)
  const unchanged = await testApp.db
    .selectFrom('fulfillments')
    .selectAll()
    .where('id', '=', fulfillment.id)
    .executeTakeFirstOrThrow()
  assert.equal(unchanged.status, 'awaiting_shipment')
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

  assert.equal(response.statusCode, 422)
  assert.match(response.body, /data-field-error="carrier"[^>]*>Enter the carrier\./)
  assert.match(response.body, /id="tracking_number"[^>]*value="RM123456789GB"/)
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

  assert.equal(response.statusCode, 422)
  assert.match(response.body, /data-form-error/)
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
  assert.match(response.headers.location ?? '', /^\/seller\/messages\/cnv_[0-9A-HJKMNP-TV-Z]{26}$/)

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

test('marking a fulfillment shipped tells fulfillment.ship naming the order', async (t) => {
  const testApp = await buildLoggedTestApp()
  const log = testApp.logLines
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const fulfillment = await createFulfillment(testApp, seller.id)

  await testApp.app.inject({
    method: 'POST',
    url: `/seller/orders/${fulfillment.id}/ship`,
    cookies: seller.cookies,
    payload: { carrier: 'Royal Mail', tracking_number: 'RM123456789GB' },
  })

  const did = log.data('fulfillment.ship', 'did')
  assert.equal(did.fulfillment_id, fulfillment.id)
  assert.equal(did.seller_id, seller.id)
  assert.equal(did.order_id, fulfillment.orderId)
  assert.equal(did.status_to, 'shipped')
  assert.equal(log.line('fulfillment.ship', 'did').actor_id, seller.id)
})

test('the order page offers the decline form while the piece has not shipped', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const fulfillment = await createFulfillment(testApp, seller.id)

  const response = await testApp.app.inject({
    method: 'GET',
    url: `/seller/orders/${fulfillment.id}`,
    cookies: seller.cookies,
  })

  assert.match(response.body, new RegExp(`action="/seller/orders/${fulfillment.id}/decline"`))
})

test('the order page drops the decline form once the piece has shipped', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const fulfillment = await createDeliveredFulfillment(testApp, seller.id)

  const response = await testApp.app.inject({
    method: 'GET',
    url: `/seller/orders/${fulfillment.id}`,
    cookies: seller.cookies,
  })

  assert.doesNotMatch(response.body, /\/decline"/)
})

test('declining refunds the customer and puts the piece back on the storefront', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const listing = await createForSaleListing(testApp, seller.id, { quantity: 1 })
  const fulfillment = await createFulfillment(testApp, seller.id, listing)

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/seller/orders/${fulfillment.id}/decline`,
    cookies: seller.cookies,
    payload: { reason: 'The piece cracked in the kiln.' },
  })

  assert.equal(response.statusCode, 302)
  const declined = await testApp.db
    .selectFrom('fulfillments')
    .select('status')
    .where('id', '=', fulfillment.id)
    .executeTakeFirstOrThrow()
  assert.equal(declined.status, 'declined')

  const restocked = await testApp.db
    .selectFrom('listings')
    .select(['quantity', 'status'])
    .where('id', '=', listing.id)
    .executeTakeFirstOrThrow()
  assert.deepEqual(restocked, { quantity: 1, status: 'for_sale' })
})

test('the declined page shows the reason and what went back', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const fulfillment = await createFulfillment(testApp, seller.id)

  await testApp.app.inject({
    method: 'POST',
    url: `/seller/orders/${fulfillment.id}/decline`,
    cookies: seller.cookies,
    payload: { reason: 'The piece cracked in the kiln.' },
  })

  const response = await testApp.app.inject({
    method: 'GET',
    url: `/seller/orders/${fulfillment.id}`,
    cookies: seller.cookies,
  })

  assert.match(response.body, /data-cell="reason"[\s\S]*The piece cracked in the kiln\./)
  assert.match(response.body, /data-cell="amount"[^>]*>\s*\$450\.00\s*</)
})

test('a decline with no reason is refused and changes nothing', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const fulfillment = await createFulfillment(testApp, seller.id)

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/seller/orders/${fulfillment.id}/decline`,
    cookies: seller.cookies,
    payload: { reason: '   ' },
  })

  assert.equal(response.statusCode, 422)
  assert.match(response.body, /data-field-error="decline_reason"[^>]*>Enter a reason\./)
  const unchanged = await testApp.db
    .selectFrom('fulfillments')
    .select('status')
    .where('id', '=', fulfillment.id)
    .executeTakeFirstOrThrow()
  assert.equal(unchanged.status, 'awaiting_shipment')
})

test('declining after shipping is refused rather than applied', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const fulfillment = await createDeliveredFulfillment(testApp, seller.id)

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/seller/orders/${fulfillment.id}/decline`,
    cookies: seller.cookies,
    payload: { reason: 'Changed my mind.' },
  })

  assert.equal(response.statusCode, 422)
  assert.match(response.body, /data-form-error/)
  const unchanged = await testApp.db
    .selectFrom('fulfillments')
    .select('status')
    .where('id', '=', fulfillment.id)
    .executeTakeFirstOrThrow()
  assert.equal(unchanged.status, 'delivered')
})

test("declining another seller's order is not found", async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const rival = await signInAsSeller(testApp, 'rival@example.com')
  const rivalFulfillment = await createFulfillment(testApp, rival.id)

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/seller/orders/${rivalFulfillment.id}/decline`,
    cookies: seller.cookies,
    payload: { reason: 'Not mine to decline.' },
  })

  assert.equal(response.statusCode, 404)
  const unchanged = await testApp.db
    .selectFrom('fulfillments')
    .select('status')
    .where('id', '=', rivalFulfillment.id)
    .executeTakeFirstOrThrow()
  assert.equal(unchanged.status, 'awaiting_shipment')
})

test('declining tells fulfillment.decline and refund.issue under one transaction', async (t) => {
  const testApp = await buildLoggedTestApp()
  const log = testApp.logLines
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const fulfillment = await createFulfillment(testApp, seller.id)

  await testApp.app.inject({
    method: 'POST',
    url: `/seller/orders/${fulfillment.id}/decline`,
    cookies: seller.cookies,
    payload: { reason: 'The piece cracked in the kiln.' },
  })

  const declined = log.data('fulfillment.decline', 'did')
  assert.equal(declined.fulfillment_id, fulfillment.id)
  assert.equal(declined.status_to, 'declined')

  const issued = log.data('refund.issue', 'did')
  assert.equal(issued.fulfillment_id, fulfillment.id)
  assert.equal(issued.amount_cents, 45_000)
  assert.equal(issued.reason, 'The piece cracked in the kiln.')
  assert.equal(typeof issued.refund_id, 'string')
  assert.equal(
    log.line('refund.issue', 'did').txn_id,
    log.line('fulfillment.decline', 'did').txn_id,
  )
  assert.equal(log.line('fulfillment.decline', 'did').actor_type, 'seller')
})
